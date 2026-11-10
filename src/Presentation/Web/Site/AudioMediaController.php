<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Shared\AudioStorage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/media/audio/{id}.mp3', name: 'media_audio', requirements: ['id' => '[0-9A-HJKMNP-TV-Z]{26}'], methods: ['GET'])]
final readonly class AudioMediaController
{
    private const int ONE_YEAR = 31536000;

    public function __construct(private AudioStorage $storage)
    {
    }

    public function __invoke(string $id): BinaryFileResponse
    {
        $response = new BinaryFileResponse($this->storage->path($id), headers: ['Content-Type' => 'audio/mpeg']);
        $response->setMaxAge(self::ONE_YEAR);
        $response->setImmutable();

        return $response;
    }
}
