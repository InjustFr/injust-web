<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\RemovePortrait\RemovePortrait;
use App\Application\Music\RemovePortrait\RemovePortraitHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/portrait', name: 'api_music_portrait_remove', methods: ['DELETE'], format: 'json')]
final class RemovePortraitController extends AbstractController
{
    public function __invoke(RemovePortraitHandler $removePortrait): JsonResponse
    {
        return $this->json($removePortrait(new RemovePortrait()));
    }
}
