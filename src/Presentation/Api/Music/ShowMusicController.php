<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\MusicQueries;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music', name: 'api_music_show', methods: ['GET'], format: 'json')]
final class ShowMusicController extends AbstractController
{
    public function __invoke(MusicQueries $queries, ClockInterface $clock): JsonResponse
    {
        return $this->json([
            'releases' => $queries->releases($clock->now()),
            'videos' => $queries->videos(),
            'text' => $queries->text(),
            'socialLinks' => $queries->socialLinks(),
        ]);
    }
}
