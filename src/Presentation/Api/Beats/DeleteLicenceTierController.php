<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\DeleteLicenceTier\DeleteLicenceTier;
use App\Application\Beats\DeleteLicenceTier\DeleteLicenceTierHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/tiers/{id}', name: 'api_beats_tier_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteLicenceTierController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteLicenceTierHandler $deleteLicenceTier): Response
    {
        $deleteLicenceTier(new DeleteLicenceTier($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
