<?php

namespace App\Enums;

enum BlockerStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
