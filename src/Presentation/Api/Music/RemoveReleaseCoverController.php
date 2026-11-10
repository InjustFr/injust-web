<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\RemoveReleaseCover\RemoveReleaseCover;
use App\Application\Music\RemoveReleaseCover\RemoveReleaseCoverHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}/cover', name: 'api_music_release_cover_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveReleaseCoverController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveReleaseCoverHandler $removeReleaseCover): JsonResponse
    {
        return $this->json($removeReleaseCover(new RemoveReleaseCover($id)));
    }
}
