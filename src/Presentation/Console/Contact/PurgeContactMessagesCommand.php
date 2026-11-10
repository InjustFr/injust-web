<?php

declare(strict_types=1);

namespace App\Presentation\Console\Contact;

use App\Application\Contact\PurgeContactMessages\PurgeContactMessages;
use App\Application\Contact\PurgeContactMessages\PurgeContactMessagesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('app:contact:purge', 'Delete the contact messages received more than three years ago')]
final readonly class PurgeContactMessagesCommand
{
    public function __construct(private PurgeContactMessagesHandler $purgeContactMessages)
    {
    }

    public function __invoke(): int
    {
        ($this->purgeContactMessages)(new PurgeContactMessages());

        return Command::SUCCESS;
    }
}
