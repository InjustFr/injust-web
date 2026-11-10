<?php

declare(strict_types=1);

namespace App\Presentation\Api\Messages;

use App\Application\Contact\MarkMessageHandled\MarkMessageHandled;
use App\Application\Contact\MarkMessageHandled\MarkMessageHandledHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/messages/{id}/handled', name: 'api_messages_handled', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class MarkMessageHandledController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] HandledPayload $payload, MarkMessageHandledHandler $markMessageHandled): JsonResponse
    {
        return $this->json($markMessageHandled(new MarkMessageHandled($id, $payload->handled)));
    }
}
