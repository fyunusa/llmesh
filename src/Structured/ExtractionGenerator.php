<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

use LLMesh\Core\Contracts\ProviderInterface;
use LLMesh\Core\Exceptions\ValidationException;
use LLMesh\Core\Generators\GenerateTextOptions;
use LLMesh\Core\Generators\TextGenerator;
use Psr\EventDispatcher\EventDispatcherInterface;
use LLMesh\Core\Events\ExtractionStarted;
use LLMesh\Core\Events\ExtractionCompleted;
use LLMesh\Core\Events\ExtractionRetrying;
use LLMesh\Core\Events\ExtractionFailed;

final class ExtractionGenerator
{
    private SchemaGenerator $schemaGenerator;
    private ModelDeserializer $deserializer;
    private ?EventDispatcherInterface $dispatcher;

    public function __construct(?EventDispatcherInterface $dispatcher = null)
    {
        $this->schemaGenerator = new SchemaGenerator();
        $this->deserializer    = new ModelDeserializer();
        $this->dispatcher      = $dispatcher;
    }

    /**
     * Extract structured data from unstructured text into a typed LLMModel instance.
     *
     * @template T of LLMModel
     * @param ProviderInterface $provider
     * @param ExtractionOptions $options
     * @return T
     * @throws ValidationException If extraction fails after all retries
     */
    public function extract(ProviderInterface $provider, ExtractionOptions $options): LLMModel
    {
        $options->validate();

        $startTime    = microtime(true);
        $providerName = $this->getProviderName($provider);

        // Dispatch ExtractionStarted event
        $this->dispatch(new ExtractionStarted($options->modelClass, $providerName, strlen($options->input)));

        $schema    = $this->schemaGenerator->generate($options->modelClass);
        $generator = new TextGenerator($provider);

        $systemPrompt = $this->buildSystemPrompt($options, $schema);
        $messages     = $this->buildMessages($options);
        $lastError    = null;
        $rawText      = null;

        for ($attempt = 1; $attempt <= $options->maxRetries; $attempt++) {
            try {
                $textOptions = GenerateTextOptions::make()
                    ->withMessages($messages)
                    ->withSystem($systemPrompt)
                    ->withTemperature($options->temperature ?? 0.1);

                if ($options->maxTokens !== null) {
                    $textOptions = $textOptions->withMaxTokens($options->maxTokens);
                }

                $response = $generator->generate($textOptions);
                $rawText  = $response->getText();

                // Strip markdown code fences if model wrapped response in ```json
                $json = $this->stripCodeFences($rawText);

                // Decode JSON
                $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($decoded)) {
                    throw new \JsonException('LLM response is not a JSON object');
                }

                // Deserialize into typed LLMModel (runs validate() internally)
                $result = $this->deserializer->deserialize($decoded, $options->modelClass);

                $durationMs = (int)((microtime(true) - $startTime) * 1000);

                // Dispatch ExtractionCompleted event
                $this->dispatch(new ExtractionCompleted($options->modelClass, $result, $attempt, $durationMs));

                return $result;
            } catch (\JsonException $e) {
                $lastError = "Invalid JSON returned: {$e->getMessage()}. Raw response: $rawText";
            } catch (ValidationException $e) {
                $lastError = 'Validation failed: ' . implode(', ', $e->errors());
            } catch (\InvalidArgumentException | \LogicException | \RuntimeException $e) {
                // Thrown by LLMModel::validate() in subclasses
                $lastError = 'Model validation failed: ' . $e->getMessage();
            }

            // Not the last attempt — send error feedback to model and retry
            if ($attempt < $options->maxRetries) {
                // Dispatch ExtractionRetrying event
                $this->dispatch(new ExtractionRetrying($options->modelClass, $attempt, $lastError));
                $messages = $this->appendCorrectionMessage($messages, $rawText, $lastError);
            }
        }

        // Dispatch ExtractionFailed event
        $this->dispatch(new ExtractionFailed($options->modelClass, $options->maxRetries, $lastError));

        // All retries exhausted
        throw new ValidationException(
            "Extraction failed after {$options->maxRetries} attempts. Last error: $lastError",
            ["Extraction failed after {$options->maxRetries} attempts. Last error: $lastError"]
        );
    }

    private function buildSystemPrompt(ExtractionOptions $options, array $schema): string
    {
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $base = $options->systemPrompt
            ?? 'You are a precise data extraction assistant.';

        return <<<PROMPT
$base

Extract the requested information from the provided text and respond with a single
valid JSON object that conforms exactly to the following JSON Schema.

JSON Schema:
```json
$schemaJson
```

Rules:
- Respond ONLY with the JSON object. No explanation, no markdown, no preamble.
- Every required field in the schema must be present in your response.
- Use null for optional fields you cannot find in the text.
- Dates must be in ISO 8601 format (e.g. "2024-03-15" or "2024-03-15T14:30:00Z").
- Enum fields must use exactly one of the allowed values listed in the schema.
- Do not invent data that is not present in the input text.
PROMPT;
    }

    private function buildMessages(ExtractionOptions $options): array
    {
        return [
            [
                'role'    => 'user',
                'content' => "Extract structured data from the following text:\n\n{$options->input}",
            ],
        ];
    }

    /**
     * Append a correction message to the conversation when a retry is needed.
     * This is the key to intelligent retry — the model sees its own error.
     */
    private function appendCorrectionMessage(array $messages, ?string $rawText, string $error): array
    {
        $messages[] = [
            'role'    => 'assistant',
            'content' => $rawText ?? '[Previous response had errors]',
        ];
        $messages[] = [
            'role'    => 'user',
            'content' => "Your previous response was invalid. Error: $error\n\nPlease try again, responding only with a valid JSON object conforming to the schema.",
        ];
        return $messages;
    }

    private function stripCodeFences(string $text): string
    {
        // Remove ```json ... ``` or ``` ... ```
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        return trim($text);
    }

    private function getProviderName(ProviderInterface $provider): string
    {
        $class = get_class($provider);

        // Handle mock objects from PHPUnit
        if (strpos($class, 'MockObject') !== false || strpos($class, 'Mock_') !== false) {
            return 'Mock';
        }

        $parts = explode('\\', $class);
        $simpleName = end($parts);

        // Remove 'Provider' suffix if present
        return str_replace('Provider', '', $simpleName);
    }

    private function dispatch(object $event): void
    {
        if ($this->dispatcher !== null) {
            $this->dispatcher->dispatch($event);
        }
    }
}
