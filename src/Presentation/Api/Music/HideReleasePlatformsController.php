<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\HideReleasePlatforms\HideReleasePlatforms;
use App\Application\Music\HideReleasePlatforms\HideReleasePlatformsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}/hidden-platforms', name: 'api_music_release_hidden_platforms', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class HideReleasePlatformsController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] HiddenPlatformsPayload $payload, HideReleasePlatformsHandler $hideReleasePlatforms): JsonResponse
    {
        return $this->json($hideReleasePlatforms(new HideReleasePlatforms($id, $payload->platforms)));
    }
}
