<?php

namespace App\Http\Resources;

use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyReport
 */
class DailyReportResource extends JsonResource
{
    public bool $withDetail = false;

    public function detailed(): static
    {
        $this->withDetail = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'date' => $this->date->toDateString(),
            'summary' => $data['summary'] ?? [],
            'narrative' => $data['narrative'] ?? '',
            'generated_at' => $this->generated_at?->toIso8601String(),
            ...($this->withDetail ? [
                'completed_tasks' => $data['completed_tasks'] ?? [],
                'by_type' => $data['by_type'] ?? [],
                'work_log' => $data['work_log'] ?? [],
                'carried_over' => $data['carried_over'] ?? [],
                'overdue_tasks' => $data['overdue_tasks'] ?? [],
                'blockers_open' => $data['blockers_open'] ?? [],
                'blockers_resolved' => $data['blockers_resolved'] ?? [],
                'goals' => $data['goals'] ?? [],
                'integration' => $data['integration'] ?? null,
                'journal' => $data['journal'] ?? null,
            ] : []),
        ];
    }
}
