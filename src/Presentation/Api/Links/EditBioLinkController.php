<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Application\Links\EditBioLink\EditBioLink;
use App\Application\Links\EditBioLink\EditBioLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/links/{id}', name: 'api_links_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditBioLinkController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] BioLinkPayload $payload, EditBioLinkHandler $editBioLink): JsonResponse
    {
        return $this->json($editBioLink(new EditBioLink($id, $payload->label, $payload->url, $payload->icon, $payload->highlighted, $payload->visible)));
    }
}
