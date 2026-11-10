<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\SettingsQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/legal', name: 'api_legal_list', methods: ['GET'], format: 'json')]
final class ListLegalDocumentsController extends AbstractController
{
    public function __invoke(SettingsQueries $queries): JsonResponse
    {
        return $this->json($queries->legalDocuments());
    }
}
