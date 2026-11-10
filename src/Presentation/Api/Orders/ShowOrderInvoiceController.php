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

#[Route('/api/admin/orders/{id}/invoice', name: 'api_orders_invoice', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
final class ShowOrderInvoiceController extends AbstractController
{
    public function __invoke(string $id, SalesQueries $queries, SalesDocuments $documents): Response
    {
        $invoice = $queries->invoice($id) ?? throw new NotFound('invoice', $id);

        return new Response($documents->invoicePdf($invoice), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'invoice-'.$invoice->number.'.pdf'),
        ]);
    }
}
