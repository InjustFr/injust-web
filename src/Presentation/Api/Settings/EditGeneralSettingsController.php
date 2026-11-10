<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\EditGeneralSettings\EditGeneralSettings;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettingsHandler;
use App\Domain\Settings\VatMentions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/settings/general', name: 'api_settings_general', methods: ['PUT'], format: 'json')]
final class EditGeneralSettingsController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] GeneralSettingsPayload $payload, EditGeneralSettingsHandler $editGeneralSettings): JsonResponse
    {
        $vat = $payload->vatMentions;

        return $this->json($editGeneralSettings(new EditGeneralSettings(
            $payload->contactEmail,
            $payload->bookingUrl,
            new VatMentions($vat->consumer, $vat->euBusiness, $vat->nonEuBusiness),
            $payload->seoTitle,
            $payload->seoDescription,
            $payload->searchIndexing,
        )));
    }
}
