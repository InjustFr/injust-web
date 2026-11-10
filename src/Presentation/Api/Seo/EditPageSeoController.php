<?php

declare(strict_types=1);

namespace App\Presentation\Api\Seo;

use App\Application\Seo\EditPageSeo\EditPageSeo;
use App\Application\Seo\EditPageSeo\EditPageSeoHandler;
use App\Domain\Seo\PageKey;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[Route('/api/admin/seo/{key}', name: 'api_seo_edit', requirements: ['key' => new EnumRequirement(PageKey::class)], methods: ['PUT'], format: 'json')]
final class EditPageSeoController extends AbstractController
{
    public function __invoke(PageKey $key, #[MapRequestPayload] PageSeoPayload $payload, EditPageSeoHandler $editPageSeo): JsonResponse
    {
        return $this->json($editPageSeo(new EditPageSeo($key, $payload->title, $payload->description)));
    }
}
