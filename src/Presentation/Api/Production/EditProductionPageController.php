<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\EditProductionPage\EditProductionPage;
use App\Application\Production\EditProductionPage\EditProductionPageHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/production/page', name: 'api_production_page_edit', methods: ['PUT'], format: 'json')]
final class EditProductionPageController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] ProductionPagePayload $payload, EditProductionPageHandler $editProductionPage): JsonResponse
    {
        return $this->json($editProductionPage(new EditProductionPage($payload->intro, $payload->process, $payload->faq)));
    }
}
