<?php

namespace App\Enums;

enum RecurrenceType: string
{
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case BiWeekly = 'bi-weekly';
    case TriWeekly = 'tri-weekly';
    case Manual = 'manual';
    case Counted = 'counted';
}
