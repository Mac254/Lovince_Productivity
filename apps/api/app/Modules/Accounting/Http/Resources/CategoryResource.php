<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'archived' => $this->archived_at !== null,
        ];
    }
}
