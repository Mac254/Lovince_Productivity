<?php

namespace App\Enums;

/**
 * Separates build work from integration work so a finished
 * development task never has to be reopened for pending integration.
 */
enum TaskCategory: string
{
    case General = 'general';
    case Development = 'development';
    case Integration = 'integration';
}
