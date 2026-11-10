<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\DeleteSocialLink\DeleteSocialLink;
use App\Application\Music\DeleteSocialLink\DeleteSocialLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/links/{id}', name: 'api_music_link_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteSocialLinkController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteSocialLinkHandler $deleteSocialLink): Response
    {
        $deleteSocialLink(new DeleteSocialLink($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
