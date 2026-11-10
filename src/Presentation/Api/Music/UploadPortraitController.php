<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\UploadPortrait\UploadPortrait;
use App\Application\Music\UploadPortrait\UploadPortraitHandler;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/portrait', name: 'api_music_portrait_upload', methods: ['POST'], format: 'json')]
final class UploadPortraitController extends AbstractController
{
    public function __invoke(Request $request, UploadPortraitHandler $uploadPortrait): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadPortrait(new UploadPortrait($file->getPathname())));
    }
}
