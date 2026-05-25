<?php

namespace MPC\Fields;

class ID extends Field implements \JsonSerializable
{
    protected string $component = 'id-field';
    protected mixed $pivotValue = null;

    /**
     * Create a new field.
     *
     * @param string|null $name
     * @param string|null $attribute
     * @param callable|null $resolveCallback
     */
    public function __construct($name = null, $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name ?? 'ID', $attribute, $resolveCallback);
    }

    /**
     * Resolve a BIGINT ID field as a string for JavaScript compatibility.
     *
     * @return $this
     */
    public function asBigInt()
    {
        $this->resolveCallback = fn($id) => (string) $id;
        return $this;
    }

    /**
     * Hide the ID field from the interface but keep it available for operations.
     *
     * @return $this
     */
    public function hide()
    {
        $this->showOnIndex = false;
        $this->showOnDetail = false;
        $this->showOnCreation = false;
        $this->showOnUpdate = false;

        return $this;
    }

    /**
     * Prepare the field for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'pivotValue' => $this->pivotValue ?? null,
        ]);
    }
}
