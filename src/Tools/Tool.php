<?php

declare(strict_types=1);

namespace LLMesh\Core\Tools;

use LLMesh\Core\Exceptions\ToolExecutionException;
use LLMesh\Core\Exceptions\ValidationException;

/**
 * A callable tool that can be given to an LLM and executed locally.
 *
 * Usage:
 * ```php
 * $tool = Tool::make('get_weather')
 *     ->description('Get current weather for a city')
 *     ->parameters([
 *         'city' => Tool::string('City name')->required(),
 *         'unit' => Tool::enum(['celsius', 'fahrenheit'])->default('celsius'),
 *     ])
 *     ->handler(function (array $params): array {
 *         return ['temperature' => 28, 'condition' => 'sunny'];
 *     });
 *
 * $result = $tool->execute(['city' => 'London']);
 * ```
 *
 * The canonical `toArray()` format matches OpenAI's function-calling schema;
 * each provider adapter is responsible for translating it as needed.
 */
final class Tool
{
    private string $name;
    private string $description = '';
    /** @var array<string, ToolParameter> */
    private array $parameters = [];
    private ?\Closure $handler = null;

    // -------------------------------------------------------------------------
    // Private constructor — use Tool::make()
    // -------------------------------------------------------------------------

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    public static function make(string $name): self
    {
        return new self($name);
    }

    // -------------------------------------------------------------------------
    // Convenience parameter factories (delegates to ToolParameter)
    // -------------------------------------------------------------------------

    public static function string(string $description = ''): ToolParameter
    {
        return ToolParameter::string($description);
    }

    public static function integer(string $description = ''): ToolParameter
    {
        return ToolParameter::integer($description);
    }

    public static function number(string $description = ''): ToolParameter
    {
        return ToolParameter::number($description);
    }

    public static function boolean(string $description = ''): ToolParameter
    {
        return ToolParameter::boolean($description);
    }

    /**
     * @param list<scalar> $values
     */
    public static function enum(array $values, string $description = ''): ToolParameter
    {
        return ToolParameter::enum($values, $description);
    }

    // -------------------------------------------------------------------------
    // Builder
    // -------------------------------------------------------------------------

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the parameter definitions.
     *
     * @param array<string, ToolParameter> $parameters
     */
    public function parameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set the handler closure that implements the tool's logic.
     *
     * @param \Closure(array): mixed $fn
     */
    public function handler(\Closure $fn): self
    {
        $this->handler = $fn;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Validate and execute the tool with the given parameters.
     *
     * @param array<string, mixed> $params Arguments from the LLM tool call
     *
     * @return mixed The handler's return value
     *
     * @throws ValidationException       If required parameters are missing
     * @throws ToolExecutionException    If the handler throws any exception
     * @throws \BadMethodCallException   If no handler was configured
     */
    public function execute(array $params): mixed
    {
        if ($this->handler === null) {
            throw new \BadMethodCallException(
                "Tool \"{$this->name}\" has no handler configured."
            );
        }

        // Validate required parameters before calling the handler
        $this->validateRequiredParams($params);

        try {
            return ($this->handler)($params);
        } catch (ToolExecutionException $e) {
            throw $e; // already wrapped — rethrow as-is
        } catch (\Throwable $e) {
            throw new ToolExecutionException(
                "Tool \"{$this->name}\" execution failed: " . $e->getMessage(),
                $this->name,
                previous: $e,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Return the JSON Schema object for the tool's parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameterSchema(): array
    {
        $properties = [];
        $required   = [];

        foreach ($this->parameters as $name => $param) {
            $properties[$name] = $param->toSchemaArray();
            if ($param->isRequired()) {
                $required[] = $name;
            }
        }

        $schema = [
            'type'       => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Return the tool definition in OpenAI canonical format:
     *
     * ```json
     * {
     *   "type": "function",
     *   "function": {
     *     "name": "...",
     *     "description": "...",
     *     "parameters": { ... JSON Schema ... }
     *   }
     * }
     * ```
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $this->name,
                'description' => $this->description,
                'parameters'  => $this->getParameterSchema(),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @throws ValidationException If a required parameter is absent from $params
     */
    private function validateRequiredParams(array $params): void
    {
        $errors = [];

        foreach ($this->parameters as $name => $param) {
            if ($param->isRequired() && !array_key_exists($name, $params)) {
                $errors[$name] = "Required parameter \"{$name}\" is missing";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException(
                'Missing required tool parameters for "' . $this->name . '": '
                    . implode(', ', array_keys($errors)),
                $errors,
            );
        }
    }
}
