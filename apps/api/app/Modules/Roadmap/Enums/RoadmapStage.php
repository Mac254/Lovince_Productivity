<?php

namespace App\Modules\Roadmap\Enums;

enum RoadmapStage: string
{
    case Now = 'now';
    case Next = 'next';
    case Later = 'later';
    case Done = 'done';
}
