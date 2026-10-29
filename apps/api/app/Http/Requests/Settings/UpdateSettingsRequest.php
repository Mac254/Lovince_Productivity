<?php

namespace App\Http\Requests\Settings;

use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A partial update shaped `{ module: { key: value } }`. Rules come from the registry, and keys
 * no module declares are rejected rather than silently dropped.
 */
class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(SettingsRegistry::class)->rules();
    }

    /**
     * @return list<\Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $registry = app(SettingsRegistry::class);
            $input = $this->all();
            if ($input === []) {
                $validator->errors()->add('settings', 'Send at least one setting to change.');
            }
            foreach ($input as $module => $values) {
                if (! $registry->has((string) $module)) {
                    $validator->errors()->add((string) $module, "There is no [{$module}] settings module.");

                    continue;
                }
                foreach (is_array($values) ? array_keys($values) : [] as $key) {
                    if (! $registry->has((string) $module, (string) $key)) {
                        $validator->errors()->add("{$module}.{$key}", "There is no [{$key}] setting in [{$module}].");
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function changes(): array
    {
        return $this->validated();
    }
}
