<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/links', name: 'api_music_link_add', methods: ['POST'], format: 'json')]
final class AddSocialLinkController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] SocialLinkPayload $payload, AddSocialLinkHandler $addSocialLink): JsonResponse
    {
        return $this->json($addSocialLink(new AddSocialLink($payload->network, $payload->url, $payload->label)), JsonResponse::HTTP_CREATED);
    }
}
