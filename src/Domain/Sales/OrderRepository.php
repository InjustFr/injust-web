<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Symfony\Component\Uid\Ulid;

interface OrderRepository
{
    public function add(Order $order): void;

    public function get(Ulid $id): Order;

    public function find(Ulid $id): ?Order;

    public function lockBySession(string $sessionId): ?Order;

    public function bySession(string $sessionId): ?Order;

    public function byPaymentIntent(string $paymentIntentId): ?Order;

    /** @return list<Order> */
    public function newestFirst(?OrderStatus $status, ?int $year): array;

    /** @return list<Order> */
    public function paidIn(int $year): array;

    public function removeExpiredBefore(\DateTimeImmutable $before): void;
}
