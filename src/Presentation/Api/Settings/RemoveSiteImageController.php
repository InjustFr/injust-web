<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\RemoveSiteImage\RemoveSiteImage;
use App\Application\Settings\RemoveSiteImage\RemoveSiteImageHandler;
use App\Domain\Settings\SiteImage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/settings/images/{slot}', name: 'api_settings_image_remove', requirements: ['slot' => new EnumRequirement(SiteImage::class)], methods: ['DELETE'], format: 'json')]
final class RemoveSiteImageController extends AbstractController
{
    public function __invoke(SiteImage $slot, RemoveSiteImageHandler $removeSiteImage): JsonResponse
    {
        return $this->json($removeSiteImage(new RemoveSiteImage($slot)));
    }
}
