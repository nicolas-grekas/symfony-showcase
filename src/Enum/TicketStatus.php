<?php

namespace App\Enum;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
}
