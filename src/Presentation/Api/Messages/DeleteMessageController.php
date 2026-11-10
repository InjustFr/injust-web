<?php

declare(strict_types=1);

namespace App\Presentation\Api\Messages;

use App\Application\Contact\DeleteMessage\DeleteMessage;
use App\Application\Contact\DeleteMessage\DeleteMessageHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/messages/{id}', name: 'api_messages_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteMessageController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteMessageHandler $deleteMessage): Response
    {
        $deleteMessage(new DeleteMessage($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
