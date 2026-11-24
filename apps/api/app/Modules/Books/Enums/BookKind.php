<?php

namespace App\Modules\Books\Enums;

enum BookKind: string
{
    case Book = 'book';
    case Podcast = 'podcast';
}
