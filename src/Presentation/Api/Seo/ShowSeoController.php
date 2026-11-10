<?php

declare(strict_types=1);

namespace App\Presentation\Api\Seo;

use App\Application\Seo\SeoQueries;
use App\Application\Settings\SettingsQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/seo', name: 'api_seo_show', methods: ['GET'], format: 'json')]
final class ShowSeoController extends AbstractController
{
    public function __invoke(SeoQueries $seo, SettingsQueries $settings): JsonResponse
    {
        $home = $settings->current();

        return $this->json(['pages' => $seo->all(), 'home' => ['title' => $home->seoTitle, 'description' => $home->seoDescription]]);
    }
}
