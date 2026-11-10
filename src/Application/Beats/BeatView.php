<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\Beat;
use App\Domain\Beats\Deliverable;

final readonly class BeatView
{
    /**
     * @param list<string>                 $tags
     * @param array<string, ?BeatFileView> $files
     */
    private function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public int $bpm,
        public string $key,
        public string $keyLabel,
        public array $tags,
        public ?string $cover,
        public ?BeatPreviewView $preview,
        public array $files,
        public bool $visible,
        public ?\DateTimeImmutable $soldExclusivelyAt,
        public bool $listed,
        public int $position,
    ) {
    }

    public static function of(Beat $beat): self
    {
        $details = $beat->details();
        $files = [];
        foreach (Deliverable::cases() as $deliverable) {
            $files[$deliverable->value] = BeatFileView::of($beat->file($deliverable));
        }

        return new self($beat->id()->toBase32(), $details->title, $details->slug, $details->bpm, $details->key->value, $details->key->label(), $details->tags, $beat->cover(), BeatPreviewView::of($beat->preview()), $files, $details->visible, $beat->soldExclusivelyAt(), $beat->isListed(), $beat->position());
    }
}
