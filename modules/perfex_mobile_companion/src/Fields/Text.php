<?php

namespace MPC\Fields;

class Text extends Field
{
    // use AsHTML;
    // use Copyable;
    // use FieldFilterable;
    // use HasSuggestions;
    // use SupportsDependentFields;
    // use SupportsMaxlength;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'text-field';

    /**
     * Prepare the element for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            // 'asHtml' => $this->asHtml,
            // 'copyable' => $this->copyable,
        ]);
    }
}
