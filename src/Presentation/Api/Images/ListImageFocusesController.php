<?php

declare(strict_types=1);

namespace App\Presentation\Api\Images;

use App\Application\Shared\ImageFocusQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/images/focus', name: 'api_image_focus_list', methods: ['GET'], format: 'json')]
final class ListImageFocusesController extends AbstractController
{
    public function __invoke(ImageFocusQueries $focuses): JsonResponse
    {
        return $this->json(array_values($focuses->all()));
    }
}
