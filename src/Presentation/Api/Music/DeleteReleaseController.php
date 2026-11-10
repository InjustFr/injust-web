<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\DeleteRelease\DeleteRelease;
use App\Application\Music\DeleteRelease\DeleteReleaseHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/releases/{id}', name: 'api_music_release_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteReleaseController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteReleaseHandler $deleteRelease): Response
    {
        $deleteRelease(new DeleteRelease($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
