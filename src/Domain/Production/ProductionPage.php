<?php

declare(strict_types=1);

namespace App\Domain\Production;

use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'production_page')]
class ProductionPage
{
    public const int ID = 1;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'intro_')]
    private LocalizedText $intro;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'process_')]
    private LocalizedText $process;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'faq_')]
    private LocalizedText $faq;

    private function __construct()
    {
        $this->intro = new LocalizedText();
        $this->process = new LocalizedText();
        $this->faq = new LocalizedText();
    }

    public static function initial(): self
    {
        return new self();
    }

    public function edit(LocalizedText $intro, LocalizedText $process, LocalizedText $faq): void
    {
        $this->intro = $intro;
        $this->process = $process;
        $this->faq = $faq;
    }

    public function intro(): LocalizedText
    {
        return $this->intro;
    }

    public function process(): LocalizedText
    {
        return $this->process;
    }

    public function faq(): LocalizedText
    {
        return $this->faq;
    }
}
