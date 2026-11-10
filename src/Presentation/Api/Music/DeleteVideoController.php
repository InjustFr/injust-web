<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\DeleteVideo\DeleteVideo;
use App\Application\Music\DeleteVideo\DeleteVideoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/videos/{id}', name: 'api_music_video_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteVideoController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteVideoHandler $deleteVideo): Response
    {
        $deleteVideo(new DeleteVideo($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
