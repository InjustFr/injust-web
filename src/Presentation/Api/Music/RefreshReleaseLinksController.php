<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinks;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinksHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}/links/refresh', name: 'api_music_release_links_refresh', requirements: ['id' => Requirement::ULID], methods: ['POST'], format: 'json')]
final class RefreshReleaseLinksController extends AbstractController
{
    public function __invoke(Ulid $id, RefreshReleaseLinksHandler $refreshReleaseLinks): JsonResponse
    {
        return $this->json($refreshReleaseLinks(new RefreshReleaseLinks($id)));
    }
}
