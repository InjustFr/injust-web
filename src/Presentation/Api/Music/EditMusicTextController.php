<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/text', name: 'api_music_text_edit', methods: ['PUT'], format: 'json')]
final class EditMusicTextController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] MusicTextPayload $payload, EditMusicTextHandler $editMusicText): JsonResponse
    {
        return $this->json($editMusicText(new EditMusicText($payload->tagline, $payload->manifesto, $payload->bio, $payload->hooks)));
    }
}
