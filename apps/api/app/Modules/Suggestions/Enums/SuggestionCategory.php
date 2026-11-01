<?php

namespace App\Modules\Suggestions\Enums;

enum SuggestionCategory: string
{
    case Focus = 'focus';
    case Growth = 'growth';
    case Health = 'health';
    case Wellbeing = 'wellbeing';
    case Social = 'social';
    case Fun = 'fun';
}
