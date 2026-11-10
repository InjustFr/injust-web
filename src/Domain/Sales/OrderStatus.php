<?php

declare(strict_types=1);

namespace App\Domain\Sales;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
    case Refunded = 'refunded';
}
