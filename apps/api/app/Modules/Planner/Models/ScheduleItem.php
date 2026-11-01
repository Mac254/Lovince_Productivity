<?php

namespace App\Modules\Planner\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'task_type_id', 'goal_id', 'start_minute', 'minutes'])]
class ScheduleItem extends Model
{
    protected function casts(): array
    {
        return ['start_minute' => 'integer', 'minutes' => 'integer'];
    }
}
