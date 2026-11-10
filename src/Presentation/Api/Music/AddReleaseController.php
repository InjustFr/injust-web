<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/releases', name: 'api_music_release_add', methods: ['POST'], format: 'json')]
final class AddReleaseController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] ReleasePayload $payload, AddReleaseHandler $addRelease): JsonResponse
    {
        return $this->json($addRelease(new AddRelease($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
