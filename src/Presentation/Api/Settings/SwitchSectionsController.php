<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\SwitchSections\SwitchSections;
use App\Application\Settings\SwitchSections\SwitchSectionsHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/settings/sections', name: 'api_settings_sections', methods: ['PUT'], format: 'json')]
final class SwitchSectionsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] SectionsPayload $payload, SwitchSectionsHandler $switchSections): JsonResponse
    {
        return $this->json($switchSections(new SwitchSections($payload->lessons, $payload->production, $payload->beats)));
    }
}
