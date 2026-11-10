<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\EditSocialLink\EditSocialLink;
use App\Application\Music\EditSocialLink\EditSocialLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/music/links/{id}', name: 'api_music_link_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditSocialLinkController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] SocialLinkPayload $payload, EditSocialLinkHandler $editSocialLink): JsonResponse
    {
        return $this->json($editSocialLink(new EditSocialLink($id, $payload->network, $payload->url, $payload->label)));
    }
}
