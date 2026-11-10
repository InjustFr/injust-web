<?php

declare(strict_types=1);

namespace App\Presentation\Api\Images;

use App\Application\Shared\ImageFocusQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/admin/images/{id}/focus', name: 'api_image_focus_show', requirements: ['id' => Requirement::ULID], methods: ['GET'], format: 'json')]
final class ShowImageFocusController extends AbstractController
{
    public function __invoke(string $id, ImageFocusQueries $focuses): JsonResponse
    {
        return $this->json($focuses->of($id));
    }
}
