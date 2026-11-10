<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\Uid\Ulid;

final class InMemoryOrders implements OrderRepository
{
    /** @var array<string, Order> */
    public array $orders = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function add(Order $order): void
    {
        $this->orders[$order->id()->toBase32()] = $order;
    }

    public function get(Ulid $id): Order
    {
        return $this->find($id) ?? throw new NotFound('order', $id->toBase32());
    }

    public function find(Ulid $id): ?Order
    {
        return $this->orders[$id->toBase32()] ?? null;
    }

    public function lockBySession(string $sessionId): ?Order
    {
        $this->journal->write('lock order '.$sessionId);

        return $this->bySession($sessionId);
    }

    public function bySession(string $sessionId): ?Order
    {
        return array_find($this->orders, static fn (Order $order): bool => $order->checkoutSessionId() === $sessionId);
    }

    public function byPaymentIntent(string $paymentIntentId): ?Order
    {
        return array_find($this->orders, static fn (Order $order): bool => $order->paymentIntentId() === $paymentIntentId);
    }

    public function lockByPaymentIntent(string $paymentIntentId): ?Order
    {
        $this->journal->write('lock order '.$paymentIntentId);

        return $this->byPaymentIntent($paymentIntentId);
    }

    public function byReference(string $reference): ?Order
    {
        return array_find($this->orders, static fn (Order $order): bool => $order->reference() === $reference);
    }

    public function lockByReference(string $reference): ?Order
    {
        $this->journal->write('lock order '.$reference);

        return $this->byReference($reference);
    }

    public function newestFirst(?OrderStatus $status, ?int $year): array
    {
        throw new \LogicException('Covered by the Doctrine repository tests.');
    }

    public function paidIn(int $year): array
    {
        throw new \LogicException('Covered by the Doctrine repository tests.');
    }

    public function removeExpiredBefore(\DateTimeImmutable $before): void
    {
        throw new \LogicException('Covered by the purge command test.');
    }
}
