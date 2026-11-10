<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\RemoveBeatCover\RemoveBeatCover;
use App\Application\Beats\RemoveBeatCover\RemoveBeatCoverHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/cover', name: 'api_beats_cover_remove', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class RemoveBeatCoverController extends AbstractController
{
    public function __invoke(Ulid $id, RemoveBeatCoverHandler $removeBeatCover): JsonResponse
    {
        return $this->json($removeBeatCover(new RemoveBeatCover($id)));
    }
}
