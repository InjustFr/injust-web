<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Shared\ImageStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/media/images/{file}', name: 'media_image', requirements: ['file' => '[0-9A-HJKMNP-TV-Z]{26}\.webp'], methods: ['GET'])]
final readonly class MediaController
{
    private const int ONE_YEAR = 31536000;

    public function __construct(private ImageStorage $storage)
    {
    }

    public function __invoke(string $file): BinaryFileResponse
    {
        $path = $this->storage->path($file) ?? throw new NotFoundHttpException();

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => 'image/webp']);
        $response->setPublic();
        $response->setMaxAge(self::ONE_YEAR);
        $response->setImmutable();

        return $response;
    }
}
