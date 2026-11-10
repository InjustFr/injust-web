<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\EditLicenceTier\EditLicenceTier;
use App\Application\Beats\EditLicenceTier\EditLicenceTierHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/tiers/{id}', name: 'api_beats_tier_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditLicenceTierController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] LicenceTierPayload $payload, EditLicenceTierHandler $editLicenceTier): JsonResponse
    {
        return $this->json($editLicenceTier(new EditLicenceTier($id, $payload->details())));
    }
}
