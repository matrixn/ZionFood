<?php

namespace App\Domain\Orders;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function transitions(string $fulfillment): array
    {
        return match ($this) {
            self::Pending => [self::Accepted, self::Rejected, self::Cancelled],
            self::Accepted => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Ready],
            self::Ready => [$fulfillment === 'delivery' ? self::OutForDelivery : self::Completed],
            self::OutForDelivery => [self::Completed],
            default => [],
        };
    }
}
