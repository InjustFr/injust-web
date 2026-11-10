<?php

declare(strict_types=1);

namespace App\Presentation\Console\Music;

use App\Application\Music\MusicQueries;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinks;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinksHandler;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Ulid;

#[AsCommand('app:music:refresh-links', 'Find the platform links of every release whose UPC is due for a check')]
final readonly class RefreshReleaseLinksCommand
{
    public function __construct(
        private MusicQueries $music,
        private RefreshReleaseLinksHandler $refreshReleaseLinks,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OutputInterface $output): int
    {
        $due = $this->music->dueForLinkRefresh($this->clock->now());
        if ([] === $due) {
            $output->writeln('No release links to refresh.');
        }
        foreach ($due as $id) {
            $refreshed = ($this->refreshReleaseLinks)(new RefreshReleaseLinks(Ulid::fromString($id)));
            $outcomes = array_map(static fn (string $platform, string $outcome): string => $platform.' '.$outcome, array_keys($refreshed->report), $refreshed->report);
            $output->writeln($refreshed->release->slug.': '.implode(', ', $outcomes));
        }

        return Command::SUCCESS;
    }
}
