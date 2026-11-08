<?php

namespace App\Modules\Books\Enums;

enum BookStatus: string
{
    case ToFind = 'to_find';
    case ToRead = 'to_read';
    case Reading = 'reading';
    case Finished = 'finished';

    /** Not started yet: still to find, or on the shelf. */
    public function isWaiting(): bool
    {
        return $this === self::ToFind || $this === self::ToRead;
    }
}
