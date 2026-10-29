<?php

namespace App\Settings;

/**
 * One setting a module declares: its key, the values it accepts and its default.
 * Built with `toggle()` or `choice()`; the registry validates, stores and serves it.
 */
final class Setting
{
    /**
     * @param  array<string, string>  $options  Value => label, for a choice.
     */
    private function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly string $label,
        public readonly bool|string $default,
        public readonly array $options = [],
        public readonly ?string $description = null,
        public readonly ?string $pattern = null,
        public readonly bool $listed = true,
        private readonly string $patternType = 'string',
    ) {}

    public static function toggle(string $key, string $label, bool $default, ?string $description = null): self
    {
        return new self($key, 'boolean', $label, $default, description: $description);
    }

    /**
     * @param  array<string, string>  $options  Value => label.
     */
    public static function choice(string $key, string $label, array $options, string $default, ?string $description = null): self
    {
        return new self($key, 'choice', $label, $default, $options, $description);
    }

    /**
     * Also accept values matching a pattern, such as references to records the user adds.
     * `$tsType` describes them in the generated contract, e.g. `` `custom:${number}` ``.
     */
    public function orMatching(string $pattern, string $tsType = 'string'): self
    {
        return new self($this->key, $this->type, $this->label, $this->default, $this->options, $this->description, $pattern, $this->listed, $tsType);
    }

    /** Kept and served, but changed elsewhere in the app rather than on the Settings page. */
    public function unlisted(): self
    {
        return new self($this->key, $this->type, $this->label, $this->default, $this->options, $this->description, $this->pattern, false, $this->patternType);
    }

    public function accepts(mixed $value): bool
    {
        if ($this->type === 'boolean') {
            return is_bool($value);
        }

        return is_string($value)
            && (array_key_exists($value, $this->options) || ($this->pattern !== null && preg_match($this->pattern, $value) === 1));
    }

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        return $this->type === 'boolean'
            ? ['sometimes', 'boolean:strict']
            : ['sometimes', 'string', 'max:64', fn (string $attribute, mixed $value, \Closure $fail) => $this->accepts($value)
                ?: $fail('Choose one of the listed options.')];
    }

    /** The TypeScript type of the value, for the generated contract. */
    public function tsType(): string
    {
        if ($this->type === 'boolean') {
            return 'boolean';
        }

        $values = array_map(fn (string $value) => "'{$value}'", array_map('strval', array_keys($this->options)));
        if ($this->pattern !== null) {
            $values[] = $this->patternType;
        }

        return implode(' | ', $values);
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(mixed $value): array
    {
        return array_filter([
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'options' => $this->type === 'choice'
                ? array_map(fn ($option, string $label) => ['value' => (string) $option, 'label' => $label], array_keys($this->options), $this->options)
                : null,
            'default' => $this->default,
            'value' => $value,
            'listed' => $this->listed,
        ], fn ($item) => $item !== null);
    }
}
