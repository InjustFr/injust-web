<?php

declare(strict_types=1);

namespace App\Presentation\Api\Seo;

use App\Application\Seo\UploadPageSeoImage\UploadPageSeoImage;
use App\Application\Seo\UploadPageSeoImage\UploadPageSeoImageHandler;
use App\Domain\Seo\PageKey;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/seo/{key}/image', name: 'api_seo_image_upload', requirements: ['key' => new EnumRequirement(PageKey::class)], methods: ['POST'], format: 'json')]
final class UploadPageSeoImageController extends AbstractController
{
    public function __invoke(PageKey $key, Request $request, UploadPageSeoImageHandler $uploadPageSeoImage): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadPageSeoImage(new UploadPageSeoImage($key, $file->getPathname())));
    }
}
