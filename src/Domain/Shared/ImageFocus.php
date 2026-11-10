<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exception\InvalidFocus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'image_focus')]
class ImageFocus
{
    public const int CENTER = 50;
    public const int STEP = 5;
    private const int EDGE = 100;

    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $image;

    #[ORM\Column(type: 'smallint')]
    private int $x = self::CENTER;

    #[ORM\Column(type: 'smallint')]
    private int $y = self::CENTER;

    private function __construct(string $image)
    {
        $this->image = $image;
    }

    public static function centered(string $image): self
    {
        return new self($image);
    }

    public function moveTo(int $x, int $y): void
    {
        if (!self::onGrid($x) || !self::onGrid($y)) {
            throw new InvalidFocus(self::STEP);
        }
        $this->x = $x;
        $this->y = $y;
    }

    public function isCentered(): bool
    {
        return self::CENTER === $this->x && self::CENTER === $this->y;
    }

    public function image(): string
    {
        return $this->image;
    }

    public function x(): int
    {
        return $this->x;
    }

    public function y(): int
    {
        return $this->y;
    }

    private static function onGrid(int $value): bool
    {
        return $value >= 0 && $value <= self::EDGE && 0 === $value % self::STEP;
    }
}
