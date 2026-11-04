<?php

namespace App\Modules\Users\Http\Resources;

use App\Modules\Users\Models\User;
use App\Modules\Users\Services\AccountTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => AccountTimezone::of($this->resource),
            // Served through the API to its owner only; the version busts the browser cache.
            'avatar_url' => $this->avatar_path ? '/api/v1/profile/avatar?v='.$this->updated_at?->timestamp : null,
        ];
    }
}
