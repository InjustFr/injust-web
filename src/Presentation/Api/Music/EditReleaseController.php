<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\EditRelease\EditRelease;
use App\Application\Music\EditRelease\EditReleaseHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}', name: 'api_music_release_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditReleaseController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] ReleasePayload $payload, EditReleaseHandler $editRelease): JsonResponse
    {
        return $this->json($editRelease(new EditRelease($id, $payload->details())));
    }
}
