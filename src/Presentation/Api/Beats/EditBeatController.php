<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\EditBeat\EditBeat;
use App\Application\Beats\EditBeat\EditBeatHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}', name: 'api_beats_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditBeatController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] BeatPayload $payload, EditBeatHandler $editBeat): JsonResponse
    {
        return $this->json($editBeat(new EditBeat($id, $payload->details())));
    }
}
