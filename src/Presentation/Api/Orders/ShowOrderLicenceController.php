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

#[Route('/api/admin/orders/{id}/licence', name: 'api_orders_licence', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
final class ShowOrderLicenceController extends AbstractController
{
    public function __invoke(string $id, SalesQueries $queries, SalesDocuments $documents): Response
    {
        $order = $queries->order($id);
        if (null === $order?->licence) {
            throw new NotFound('licence', $id);
        }

        return new Response($documents->licence($order), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, 'licence-'.$order->reference.'.pdf'),
        ]);
    }
}
