<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Application\Links\AddBioLink\AddBioLink;
use App\Application\Links\AddBioLink\AddBioLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/links', name: 'api_links_add', methods: ['POST'], format: 'json')]
final class AddBioLinkController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] BioLinkPayload $payload, AddBioLinkHandler $addBioLink): JsonResponse
    {
        return $this->json($addBioLink(new AddBioLink($payload->label, $payload->url, $payload->icon, $payload->highlighted, $payload->visible)), JsonResponse::HTTP_CREATED);
    }
}
