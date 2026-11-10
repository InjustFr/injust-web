<?php

declare(strict_types=1);

namespace App\Presentation\Api\Seo;

use App\Application\Seo\RemovePageSeoImage\RemovePageSeoImage;
use App\Application\Seo\RemovePageSeoImage\RemovePageSeoImageHandler;
use App\Domain\Seo\PageKey;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/seo/{key}/image', name: 'api_seo_image_remove', requirements: ['key' => new EnumRequirement(PageKey::class)], methods: ['DELETE'], format: 'json')]
final class RemovePageSeoImageController extends AbstractController
{
    public function __invoke(PageKey $key, RemovePageSeoImageHandler $removePageSeoImage): JsonResponse
    {
        return $this->json($removePageSeoImage(new RemovePageSeoImage($key)));
    }
}
