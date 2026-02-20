<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Manual = 'manual';
    case Recurring = 'recurring';
}
