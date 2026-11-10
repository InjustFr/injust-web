<?php

declare(strict_types=1);

namespace App\Application\Sales\RequestWithdrawal;

use App\Application\Atomic;
use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;
use App\Application\Sales\WithdrawalRequestView;
use App\Application\Transaction;
use App\Domain\Sales\Exception\NoMatchingOrder;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Sales\WithdrawalRequestRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final readonly class RequestWithdrawalHandler
{
    public function __construct(
        private OrderRepository $orders,
        private WithdrawalRequestRepository $withdrawals,
        private SalesMails $mails,
        private ClockInterface $clock,
        private Atomic $atomic,
        private Transaction $transaction,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestWithdrawal $command): WithdrawalRequestView
    {
        [$order, $request] = $this->atomic->atomically(fn (): array => $this->withdraw($command));
        try {
            $this->mails->withdrawalAcknowledgement(OrderView::of($order, $request), WithdrawalRequestView::of($request));
            $request->acknowledged($this->clock->now());
            $this->transaction->commit();
        } catch (\Exception $failure) {
            $this->unsent('acknowledgement', $order, $failure);
        }
        try {
            $this->mails->withdrawalNotice(OrderView::of($order, $request), WithdrawalRequestView::of($request));
        } catch (\Exception $failure) {
            $this->unsent('notice', $order, $failure);
        }

        return WithdrawalRequestView::of($request);
    }

    private function unsent(string $mail, Order $order, \Exception $failure): void
    {
        $this->logger->error('The withdrawal {mail} of order {reference} was not sent: {error}', ['mail' => $mail, 'reference' => $order->reference(), 'error' => $failure::class]);
    }

    /** @return array{Order, WithdrawalRequest} */
    private function withdraw(RequestWithdrawal $command): array
    {
        $order = $this->orders->lockByReference(strtoupper(trim($command->reference)));
        if (null === $order || !$order->belongsTo($command->email)) {
            throw new NoMatchingOrder();
        }
        $now = $this->clock->now();
        $order->withdraw($now);
        $request = WithdrawalRequest::receive($order, $command->email, $command->name, $command->reason, $command->locale, $now);
        $this->withdrawals->add($request);
        $this->transaction->commit();

        return [$order, $request];
    }
}
