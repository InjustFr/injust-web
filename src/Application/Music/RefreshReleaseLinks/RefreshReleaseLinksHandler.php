<?php

declare(strict_types=1);

namespace App\Application\Music\RefreshReleaseLinks;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Application\Music\RefreshReport;
use App\Application\Music\ReleaseLookup;
use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\Exception\MissingUpc;
use App\Domain\Music\ReleaseRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class RefreshReleaseLinksHandler
{
    /** @param iterable<PlatformFinder> $finders */
    public function __construct(
        private ReleaseRepository $releases,
        #[AutowireIterator(PlatformFinder::class)]
        private iterable $finders,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RefreshReleaseLinks $command): RefreshReport
    {
        $release = $this->releases->get($command->id);
        $lookup = new ReleaseLookup($release->upc() ?? throw new MissingUpc(), $release->title(), $release->type());
        $report = [];
        $found = [];
        foreach ($this->finders as $finder) {
            $result = $this->lookup($finder, $lookup);
            $report[$finder->platform()->value] = $result->outcome->value;
            $link = $result->link($finder->platform());
            if (null !== $link) {
                $found[] = $link;
            }
        }
        $now = $this->clock->now();
        $release->applyFoundLinks($found, $now);
        $this->transaction->commit();

        return new RefreshReport(ReleaseView::of($release, $now), $report);
    }

    private function lookup(PlatformFinder $finder, ReleaseLookup $release): FinderResult
    {
        if (!$finder->configured()) {
            return FinderResult::notConfigured();
        }
        try {
            return $finder->find($release);
        } catch (\Throwable $failure) {
            $this->logger->warning('Platform lookup failed.', ['platform' => $finder->platform()->value, 'exception' => $failure::class]);

            return FinderResult::failed();
        }
    }
}
