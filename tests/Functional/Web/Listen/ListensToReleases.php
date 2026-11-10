<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Listen;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinks;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinksHandler;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Uid\Ulid;

trait ListensToReleases
{
    /** @param list<PlatformLink> $links */
    private static function release(string $title = 'White Men are the Issue', ?string $upc = null, array $links = [], string $date = '2026-06-01', ReleaseType $type = ReleaseType::Single, ?string $smartLinkUrl = null): string
    {
        return self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails($title, null, $type, new \DateTimeImmutable($date), new LocalizedText(), [], $links, $smartLinkUrl, null, $upc)))->id;
    }

    private static function refresh(string $id): void
    {
        self::getContainer()->get(RefreshReleaseLinksHandler::class)(new RefreshReleaseLinks(Ulid::fromString($id)));
    }
}
