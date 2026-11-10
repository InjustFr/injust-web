<?php

declare(strict_types=1);

namespace App\Domain\Beats;

use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'beats_page')]
class BeatsPage
{
    public const int ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'intro_')]
    private LocalizedText $intro;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'faq_')]
    private LocalizedText $faq;

    private function __construct()
    {
        $this->intro = new LocalizedText();
        $this->faq = new LocalizedText();
    }

    public static function initial(): self
    {
        return new self();
    }

    public function edit(LocalizedText $intro, LocalizedText $faq): void
    {
        $this->intro = $intro;
        $this->faq = $faq;
    }

    public function intro(): LocalizedText
    {
        return $this->intro;
    }

    public function faq(): LocalizedText
    {
        return $this->faq;
    }
}
