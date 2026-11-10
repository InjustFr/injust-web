<?php

declare(strict_types=1);

namespace App\Presentation\Api\Messages;

use App\Application\Contact\ContactQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/messages', name: 'api_messages_list', methods: ['GET'], format: 'json')]
final class ListMessagesController extends AbstractController
{
    public function __invoke(ContactQueries $queries, #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] MessageStatus $status = MessageStatus::Open): JsonResponse
    {
        return $this->json($queries->messages(MessageStatus::All === $status));
    }
}
