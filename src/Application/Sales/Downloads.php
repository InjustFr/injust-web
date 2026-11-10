<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Application\Shared\PrivateFileStorage;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\Uid\Ulid;

final readonly class Downloads
{
    public function __construct(
        private SalesQueries $sales,
        private BeatRepository $beats,
        private PrivateFileStorage $files,
    ) {
    }

    public function order(string $orderId): ?OrderView
    {
        $order = $this->sales->order($orderId);

        return null === $order?->licence ? null : $order;
    }

    public function file(OrderView $order, Deliverable $deliverable): ?DownloadFile
    {
        $licence = $order->licence?->beat;
        if (null === $licence || !$licence->delivers($deliverable)) {
            return null;
        }
        $file = $this->beats->find(Ulid::fromString($licence->beatId))?->file($deliverable);
        if (null === $file) {
            return null;
        }
        try {
            $path = $this->files->path($file->id);
        } catch (NotFound) {
            return null;
        }
        $format = $deliverable->format();

        return new DownloadFile($path, $licence->slug.'-'.$deliverable->value.'.'.$format->value, $format->mimeType());
    }
}
