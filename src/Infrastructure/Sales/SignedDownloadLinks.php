<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\DownloadLink;
use App\Application\Sales\DownloadLinks;
use App\Application\Sales\OrderView;
use App\Domain\Beats\Deliverable;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SignedDownloadLinks implements DownloadLinks
{
    public function __construct(
        private UriSigner $signer,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
    ) {
    }

    public function forOrder(OrderView $order): array
    {
        return array_map(fn (Deliverable $deliverable): DownloadLink => $this->link($order, $deliverable->value, 'download', ['deliverable' => $deliverable->value]), $order->licence->beat->deliverables ?? []);
    }

    public function licence(OrderView $order): DownloadLink
    {
        return $this->link($order, self::LICENCE, 'download_licence');
    }

    /** @param array<string, string> $parameters */
    private function link(OrderView $order, string $file, string $route, array $parameters = []): DownloadLink
    {
        $expiresAt = $this->clock->now()->add(new \DateInterval(self::VALIDITY));
        $url = $this->urls->generate($route, ['orderId' => $order->id, ...$parameters], UrlGeneratorInterface::ABSOLUTE_URL);

        return new DownloadLink($file, $this->signer->sign($url, $expiresAt), $expiresAt);
    }
}
