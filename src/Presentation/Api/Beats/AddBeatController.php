<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\AddBeat\AddBeat;
use App\Application\Beats\AddBeat\AddBeatHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats', name: 'api_beats_add', methods: ['POST'], format: 'json')]
final class AddBeatController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] BeatPayload $payload, AddBeatHandler $addBeat): JsonResponse
    {
        return $this->json($addBeat(new AddBeat($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
