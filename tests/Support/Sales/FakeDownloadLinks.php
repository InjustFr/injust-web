<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\DownloadLink;
use App\Application\Sales\DownloadLinks;
use App\Application\Sales\OrderView;
use App\Domain\Beats\Deliverable;

final class FakeDownloadLinks implements DownloadLinks
{
    public function forOrder(OrderView $order): array
    {
        return array_map(static fn (Deliverable $deliverable): DownloadLink => self::link($order, $deliverable->value), $order->licence->beat->deliverables ?? []);
    }

    public function licence(OrderView $order): DownloadLink
    {
        return self::link($order, 'licence');
    }

    private static function link(OrderView $order, string $file): DownloadLink
    {
        return new DownloadLink($file, 'https://injust.test/download/'.$order->id.'/'.$file.'?_hash=signed', new \DateTimeImmutable('2026-11-17 10:06:00 UTC'));
    }
}
