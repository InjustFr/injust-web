<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\FoundLink;
use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;
use App\Domain\Shared\HttpsUrl;

final readonly class FinderResult
{
    private function __construct(
        public LookupOutcome $outcome,
        public ?string $url = null,
        public LinkSource $source = LinkSource::Auto,
    ) {
    }

    public static function found(string $url, LinkSource $source = LinkSource::Auto): self
    {
        return new self(LookupOutcome::Found, HttpsUrl::of($url), $source);
    }

    public static function notFound(): self
    {
        return new self(LookupOutcome::NotFound);
    }

    public static function failed(): self
    {
        return new self(LookupOutcome::Failed);
    }

    public static function notConfigured(): self
    {
        return new self(LookupOutcome::NotConfigured);
    }

    public function link(Platform $platform): ?FoundLink
    {
        return \in_array($this->outcome, [LookupOutcome::Found, LookupOutcome::NotFound], true) ? new FoundLink($platform, $this->url, $this->source) : null;
    }
}
