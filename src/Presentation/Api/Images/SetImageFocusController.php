<?php

declare(strict_types=1);

namespace App\Presentation\Api\Images;

use App\Application\Shared\SetImageFocus\SetImageFocus;
use App\Application\Shared\SetImageFocus\SetImageFocusHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/admin/images/{id}/focus', name: 'api_image_focus_set', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class SetImageFocusController extends AbstractController
{
    public function __invoke(string $id, #[MapRequestPayload] FocusPayload $payload, SetImageFocusHandler $setImageFocus): JsonResponse
    {
        return $this->json($setImageFocus(new SetImageFocus($id, $payload->x, $payload->y)));
    }
}
