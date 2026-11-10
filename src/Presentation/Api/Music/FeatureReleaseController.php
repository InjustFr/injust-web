<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}/featured', name: 'api_music_release_feature', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class FeatureReleaseController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] FeaturedPayload $payload, FeatureReleaseHandler $featureRelease): JsonResponse
    {
        return $this->json($featureRelease(new FeatureRelease($id, $payload->featured)));
    }
}
