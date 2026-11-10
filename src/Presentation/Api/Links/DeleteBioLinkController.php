<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Application\Links\DeleteBioLink\DeleteBioLink;
use App\Application\Links\DeleteBioLink\DeleteBioLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/links/{id}', name: 'api_links_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteBioLinkController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteBioLinkHandler $deleteBioLink): Response
    {
        $deleteBioLink(new DeleteBioLink($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
