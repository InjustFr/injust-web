<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\EditBeatsPage\EditBeatsPage;
use App\Application\Beats\EditBeatsPage\EditBeatsPageHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats/page', name: 'api_beats_page_edit', methods: ['PUT'], format: 'json')]
final class EditBeatsPageController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] BeatsPagePayload $payload, EditBeatsPageHandler $editBeatsPage): JsonResponse
    {
        return $this->json($editBeatsPage(new EditBeatsPage($payload->intro, $payload->faq)));
    }
}
