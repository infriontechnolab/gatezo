<?php

namespace App\Enums;

/** What a synced scan was: a gate check-in or a goodies-counter handout. Sent by the phone. */
enum ScanKind: string
{
    case Checkin = 'checkin';
    case Goodies = 'goodies';
}
