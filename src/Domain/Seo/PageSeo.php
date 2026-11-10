<?php

declare(strict_types=1);

namespace App\Domain\Seo;

use App\Domain\Seo\Exception\InvalidPageSeo;
use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'page_seo')]
class PageSeo
{
    public const int MAX_TITLE = 70;
    public const int MAX_DESCRIPTION = 170;

    #[ORM\Id]
    #[ORM\Column(name: 'page_key', length: 20, enumType: PageKey::class)]
    private PageKey $key;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'title_')]
    private LocalizedText $title;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'description_')]
    private LocalizedText $description;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $image = null;

    private function __construct(PageKey $key)
    {
        $this->key = $key;
        $this->title = new LocalizedText();
        $this->description = new LocalizedText();
    }

    public static function blank(PageKey $key): self
    {
        return new self($key);
    }

    public function edit(LocalizedText $title, LocalizedText $description): void
    {
        if (self::longerThan($title, self::MAX_TITLE) || self::longerThan($description, self::MAX_DESCRIPTION)) {
            throw new InvalidPageSeo(self::MAX_TITLE, self::MAX_DESCRIPTION);
        }
        $this->title = $title;
        $this->description = $description;
    }

    public function useImage(string $image): ?string
    {
        $previous = $this->image;
        $this->image = $image;

        return $previous;
    }

    public function removeImage(): ?string
    {
        $previous = $this->image;
        $this->image = null;

        return $previous;
    }

    public function key(): PageKey
    {
        return $this->key;
    }

    public function title(): LocalizedText
    {
        return $this->title;
    }

    public function description(): LocalizedText
    {
        return $this->description;
    }

    public function image(): ?string
    {
        return $this->image;
    }

    private static function longerThan(LocalizedText $text, int $max): bool
    {
        return max(mb_strlen($text->fr), mb_strlen($text->en)) > $max;
    }
}
