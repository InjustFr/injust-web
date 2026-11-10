<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\SalesDocuments;
use App\Application\Sales\SalesQueries;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/admin/orders/{id}/credit-notes/{creditNoteId}', name: 'api_orders_credit_note', requirements: ['id' => Requirement::ULID, 'creditNoteId' => Requirement::ULID], methods: ['GET'])]
final class ShowCreditNoteController extends AbstractController
{
    public function __invoke(string $id, string $creditNoteId, SalesQueries $queries, SalesDocuments $documents): Response
    {
        $note = $queries->creditNote($creditNoteId);
        if (null === $note || $note->orderId !== $id) {
            throw new NotFound('credit note', $creditNoteId);
        }

        return new Response($documents->creditNotePdf($note), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'credit-note-'.$note->number.'.pdf'),
        ]);
    }
}
