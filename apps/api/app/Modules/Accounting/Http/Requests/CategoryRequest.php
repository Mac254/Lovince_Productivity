<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Models\Category;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');
        if ($category instanceof Category) {
            Gate::authorize('update', $category);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category|null $category */
        $category = $this->route('category');
        $creating = $this->isMethod('POST');
        $kind = $creating ? $this->input('kind') : $category?->kind->value;

        return [
            'kind' => $creating ? ['required', Rule::enum(CategoryKind::class)] : ['prohibited'],
            'name' => [
                $creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:'.Contract::ACCOUNTING_CATEGORY_NAME_MAX,
                Rule::unique('accounting_categories', 'name')->where('user_id', $this->user()->id)->where('kind', $kind)->ignore($category?->id),
            ],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $attributes = $this->safe()->only(['kind', 'name']);
        if ($this->has('archived')) {
            $attributes['archived_at'] = $this->boolean('archived') ? now() : null;
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
