<?php

declare(strict_types=1);

namespace App\Presentation\Console\Sales;

use App\Application\Sales\PurgeAbandonedCheckouts\PurgeAbandonedCheckouts;
use App\Application\Sales\PurgeAbandonedCheckouts\PurgeAbandonedCheckoutsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('app:sales:purge', 'Delete the expired, unpaid checkouts started more than 30 days ago')]
final readonly class PurgeAbandonedCheckoutsCommand
{
    public function __construct(private PurgeAbandonedCheckoutsHandler $purgeAbandonedCheckouts)
    {
    }

    public function __invoke(): int
    {
        ($this->purgeAbandonedCheckouts)(new PurgeAbandonedCheckouts());

        return Command::SUCCESS;
    }
}
