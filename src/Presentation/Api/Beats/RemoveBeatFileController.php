<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\RemoveBeatFile\RemoveBeatFile;
use App\Application\Beats\RemoveBeatFile\RemoveBeatFileHandler;
use App\Domain\Beats\Deliverable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}/files/{deliverable}', name: 'api_beats_file_remove', requirements: ['id' => Requirement::ULID, 'deliverable' => new EnumRequirement(Deliverable::class)], methods: ['DELETE'], format: 'json')]
final class RemoveBeatFileController extends AbstractController
{
    public function __invoke(Ulid $id, Deliverable $deliverable, RemoveBeatFileHandler $removeBeatFile): JsonResponse
    {
        return $this->json($removeBeatFile(new RemoveBeatFile($id, $deliverable)));
    }
}
