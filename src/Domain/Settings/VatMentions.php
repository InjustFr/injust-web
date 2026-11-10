<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class VatMentions
{
    public const int MAX_TEXT = 255;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $consumer;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $euBusiness;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $nonEuBusiness;

    public function __construct(string $consumer, string $euBusiness, string $nonEuBusiness)
    {
        $this->consumer = trim($consumer);
        $this->euBusiness = trim($euBusiness);
        $this->nonEuBusiness = trim($nonEuBusiness);
    }

    public static function defaults(): self
    {
        return new self('TVA non applicable – art. 293 B du CGI', 'Autoliquidation', 'TVA non applicable – art. 259-1 du CGI');
    }
}
