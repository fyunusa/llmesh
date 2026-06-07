<?php

declare(strict_types=1);

namespace LLMesh\Core\Structured;

abstract class LLMModel
{
    /**
     * Override in subclasses to add custom post-deserialization validation.
     * Called automatically after the model is constructed from LLM output.
     * Throw any exception to trigger a retry with the error message sent back to the LLM.
     *
     * @throws \InvalidArgumentException|\LogicException|\RuntimeException
     */
    public function validate(): void
    {
        // No-op by default. Subclasses override to add validation logic.
    }

    /**
     * Serialize this model to a plain array (recursive).
     * Useful for JSON encoding, logging, or storing the extracted data.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);
        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if (property_exists($this, $name)) {
                    $value = $this->{$name};
                    $result[$this->toSnakeCase($name)] = $this->serializeValue($value);
                }
            }
        }

        return $result;
    }

    /**
     * Serialize this model to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Check equality between two LLMModel instances by comparing their array representations.
     */
    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    private function serializeValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof self               => $value->toArray(),
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            $value instanceof \BackedEnum        => $value->value,
            $value instanceof \UnitEnum          => $value->name,
            is_array($value)                     => array_map(
                fn($item) => $this->serializeValue($item),
                $value
            ),
            default                              => $value,
        };
    }

    private function toSnakeCase(string $camelCase): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($camelCase)));
    }
}
