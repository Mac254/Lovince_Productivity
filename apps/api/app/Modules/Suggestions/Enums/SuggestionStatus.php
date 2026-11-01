<?php

namespace App\Modules\Suggestions\Enums;

enum SuggestionStatus: string
{
    case New = 'new';
    case Accepted = 'accepted';
    case Done = 'done';
    case Dismissed = 'dismissed';
}
