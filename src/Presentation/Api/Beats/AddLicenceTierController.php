<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\AddLicenceTier\AddLicenceTier;
use App\Application\Beats\AddLicenceTier\AddLicenceTierHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats/tiers', name: 'api_beats_tier_add', methods: ['POST'], format: 'json')]
final class AddLicenceTierController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] LicenceTierPayload $payload, AddLicenceTierHandler $addLicenceTier): JsonResponse
    {
        return $this->json($addLicenceTier(new AddLicenceTier($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
