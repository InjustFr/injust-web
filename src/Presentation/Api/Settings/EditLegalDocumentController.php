<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\EditLegalDocument\EditLegalDocument;
use App\Application\Settings\EditLegalDocument\EditLegalDocumentHandler;
use App\Domain\Settings\LegalKind;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/legal/{kind}', name: 'api_legal_edit', requirements: ['kind' => new EnumRequirement(LegalKind::class)], methods: ['PUT'], format: 'json')]
final class EditLegalDocumentController extends AbstractController
{
    public function __invoke(LegalKind $kind, #[MapRequestPayload] LegalDocumentPayload $payload, EditLegalDocumentHandler $editLegalDocument): JsonResponse
    {
        return $this->json($editLegalDocument(new EditLegalDocument($kind, $payload->body)));
    }
}
