<?php

declare(strict_types=1);

namespace App\Application\Music\CountReleaseLinkClick;

use App\Application\Transaction;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Shared\Exception\NotFound;

final readonly class CountReleaseLinkClickHandler
{
    public function __construct(
        private ReleaseRepository $releases,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(CountReleaseLinkClick $command): string
    {
        $release = $this->releases->bySlug($command->slug);
        $url = $release->linkTo($command->platform) ?? throw new NotFound('release_link', $command->platform->value);
        $this->releases->countClick($release->id(), $command->platform);
        $this->transaction->commit();

        return $url;
    }
}
