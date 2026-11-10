<?php

declare(strict_types=1);

namespace App\Presentation\Console\Settings;

use App\Application\Settings\InstallLegalDefaults\InstallLegalDefaults;
use App\Application\Settings\InstallLegalDefaults\InstallLegalDefaultsHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand('app:legal:install-defaults', 'Create each missing legal document from its default Markdown text')]
final readonly class InstallLegalDefaultsCommand
{
    public function __construct(private InstallLegalDefaultsHandler $installLegalDefaults)
    {
    }

    public function __invoke(): int
    {
        ($this->installLegalDefaults)(new InstallLegalDefaults());

        return Command::SUCCESS;
    }
}
