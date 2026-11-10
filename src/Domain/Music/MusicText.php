<?php

declare(strict_types=1);

namespace App\Domain\Music;

use App\Domain\Music\Exception\InvalidHooks;
use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'music_text')]
class MusicText
{
    public const int ID = 1;
    public const int MAX_HOOKS = 12;
    public const int MAX_HOOK_LENGTH = 120;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'tagline_')]
    private LocalizedText $tagline;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'manifesto_')]
    private LocalizedText $manifesto;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'bio_')]
    private LocalizedText $bio;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $hooks = [];

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $portrait = null;

    private function __construct()
    {
        $this->tagline = new LocalizedText();
        $this->manifesto = new LocalizedText();
        $this->bio = new LocalizedText();
    }

    public static function initial(): self
    {
        return new self();
    }

    /** @param list<string> $hooks */
    public function edit(LocalizedText $tagline, LocalizedText $manifesto, LocalizedText $bio, array $hooks): void
    {
        $this->hooks = self::hooksOf($hooks);
        $this->tagline = $tagline;
        $this->manifesto = $manifesto;
        $this->bio = $bio;
    }

    public function usePortrait(string $image): ?string
    {
        $previous = $this->portrait;
        $this->portrait = $image;

        return $previous;
    }

    public function removePortrait(): ?string
    {
        $previous = $this->portrait;
        $this->portrait = null;

        return $previous;
    }

    public function tagline(): LocalizedText
    {
        return $this->tagline;
    }

    public function manifesto(): LocalizedText
    {
        return $this->manifesto;
    }

    public function bio(): LocalizedText
    {
        return $this->bio;
    }

    /** @return list<string> */
    public function hooks(): array
    {
        return $this->hooks;
    }

    public function portrait(): ?string
    {
        return $this->portrait;
    }

    /**
     * @param list<string> $hooks
     *
     * @return list<string>
     */
    private static function hooksOf(array $hooks): array
    {
        $hooks = array_values(array_filter(array_map(trim(...), $hooks), static fn (string $hook): bool => '' !== $hook));
        $tooLong = array_filter($hooks, static fn (string $hook): bool => mb_strlen($hook) > self::MAX_HOOK_LENGTH);
        if (\count($hooks) > self::MAX_HOOKS || [] !== $tooLong) {
            throw new InvalidHooks(self::MAX_HOOKS, self::MAX_HOOK_LENGTH);
        }

        return $hooks;
    }
}
