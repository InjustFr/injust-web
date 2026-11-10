<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Shared\LocalizedText;

/**
 * @phpstan-import-type LicensedBeatData from LicensedBeat
 *
 * @phpstan-type LicenceData array{beat: LicensedBeatData, terms: array{version: int, body: array{fr: string, en: string}, publishedAt: string}}
 */
final readonly class LicenceSnapshot
{
    public function __construct(
        public LicensedBeat $beat,
        public Terms $terms,
    ) {
    }

    /** @param LicenceData $data */
    public static function fromArray(array $data): self
    {
        $terms = $data['terms'];

        return new self(LicensedBeat::fromArray($data['beat']), new Terms($terms['version'], new LocalizedText($terms['body']['fr'], $terms['body']['en']), new \DateTimeImmutable($terms['publishedAt'])));
    }

    /** @return LicenceData */
    public function toArray(): array
    {
        return [
            'beat' => $this->beat->toArray(),
            'terms' => ['version' => $this->terms->version, 'body' => $this->terms->body->jsonSerialize(), 'publishedAt' => $this->terms->publishedAt->format(\DATE_ATOM)],
        ];
    }
}
