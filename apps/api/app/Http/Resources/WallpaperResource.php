<?php

namespace App\Http\Resources;

use App\Models\Wallpaper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallpaper
 */
class WallpaperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Owner-only, same-origin image URL; never a public storage path.
            'url' => "/api/v1/wallpapers/{$this->id}/image",
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
