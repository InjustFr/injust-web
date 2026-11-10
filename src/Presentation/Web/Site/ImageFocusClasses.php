<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Shared\ImageFocusQueries;
use App\Application\Shared\ImageFocusView;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFunction;

final class ImageFocusClasses implements ResetInterface
{
    /** @var array<string, ImageFocusView>|null */
    private ?array $focuses = null;

    public function __construct(private readonly ImageFocusQueries $queries)
    {
    }

    #[AsTwigFunction('image_focus')]
    public function of(?string $id): string
    {
        $focus = null === $id ? null : ($this->focuses ??= $this->queries->all())[$id] ?? null;

        return null === $focus ? '' : \sprintf('fx-%d fy-%d', $focus->x, $focus->y);
    }

    public function reset(): void
    {
        $this->focuses = null;
    }
}
