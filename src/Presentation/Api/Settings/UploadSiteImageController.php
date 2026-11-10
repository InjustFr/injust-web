<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\UploadSiteImage\UploadSiteImage;
use App\Application\Settings\UploadSiteImage\UploadSiteImageHandler;
use App\Domain\Settings\SiteImage;
use App\Domain\Shared\Exception\UnsupportedImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/settings/images/{slot}', name: 'api_settings_image_upload', requirements: ['slot' => new EnumRequirement(SiteImage::class)], methods: ['POST'], format: 'json')]
final class UploadSiteImageController extends AbstractController
{
    public function __invoke(SiteImage $slot, Request $request, UploadSiteImageHandler $uploadSiteImage): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnsupportedImage();
        }

        return $this->json($uploadSiteImage(new UploadSiteImage($slot, $file->getPathname())));
    }
}
