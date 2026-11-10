<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use App\Application\Sales\Downloads;
use App\Application\Sales\OrderView;
use App\Domain\Sales\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DownloadGate
{
    public function __construct(
        private UriSigner $signer,
        private Downloads $downloads,
    ) {
    }

    public function open(Request $request, string $orderId): OrderView
    {
        try {
            $this->signer->verify($request);
        } catch (ExpiredSignedUriException $expired) {
            throw new DownloadLinkExpired($this->downloads->order($orderId)?->reference, $expired);
        } catch (SignedUriException $refused) {
            throw new AccessDeniedHttpException(previous: $refused);
        }
        $order = $this->downloads->order($orderId) ?? throw new NotFoundHttpException();

        return OrderStatus::Paid->value === $order->status ? $order : throw new AccessDeniedHttpException();
    }
}
