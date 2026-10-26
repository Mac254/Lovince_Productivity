<?php

namespace App\Enums;

/**
 * Identity colours for goals ("jobs"). Validated as a categorical palette:
 * distinct for colour-blind readers and >= 3:1 against the surface.
 */
enum GoalTone: string
{
    case Green = 'green';
    case Blue = 'blue';
    case Amber = 'amber';
    case Violet = 'violet';
    case Rose = 'rose';
    case Cyan = 'cyan';
}
