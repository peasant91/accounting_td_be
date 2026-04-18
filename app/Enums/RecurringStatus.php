<?php

namespace App\Enums;

enum RecurringStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Completed = 'completed';
    case Terminated = 'terminated';
}
