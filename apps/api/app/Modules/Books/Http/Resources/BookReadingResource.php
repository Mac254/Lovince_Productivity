<?php

namespace App\Modules\Books\Http\Resources;

use App\Modules\Books\Models\BookReading;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookReading
 */
class BookReadingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_id' => $this->book_id,
            'read_on' => $this->read_on->toDateString(),
            'chapters' => $this->chapters,
            'minutes' => $this->minutes,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
