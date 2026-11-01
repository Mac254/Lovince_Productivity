<?php

namespace App\Modules\Suggestions\Enums;

enum SuggestionSource: string
{
    case Insight = 'insight';
    case Library = 'library';
    case Custom = 'custom';
}
