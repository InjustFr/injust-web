<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\EditLegalIdentity\EditLegalIdentity;
use App\Application\Settings\EditLegalIdentity\EditLegalIdentityHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/settings/legal-identity', name: 'api_settings_legal_identity', methods: ['PUT'], format: 'json')]
final class EditLegalIdentityController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] LegalIdentityPayload $payload, EditLegalIdentityHandler $editLegalIdentity): JsonResponse
    {
        return $this->json($editLegalIdentity(new EditLegalIdentity(
            $payload->name,
            $payload->businessForm,
            $payload->siren,
            $payload->address,
            $payload->email,
            $payload->phone,
            $payload->publicationDirector,
            $payload->mediatorName,
            $payload->mediatorUrl,
            $payload->hostName,
            $payload->hostAddress,
        )));
    }
}
