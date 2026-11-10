<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\RemoveBeatPreview\RemoveBeatPreview;
use App\Application\Beats\RemoveBeatPreview\RemoveBeatPreviewHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/preview', name: 'api_beats_preview_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveBeatPreviewController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveBeatPreviewHandler $removeBeatPreview): JsonResponse
    {
        return $this->json($removeBeatPreview(new RemoveBeatPreview($id)));
    }
}
