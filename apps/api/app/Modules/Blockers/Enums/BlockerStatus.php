<?php

namespace App\Modules\Blockers\Enums;

enum BlockerStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
