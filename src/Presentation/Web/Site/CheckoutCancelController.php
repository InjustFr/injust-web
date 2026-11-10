<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Sales\SalesQueries;
use App\Domain\Sales\ProductType;
use App\Presentation\Web\Security\SecurityHeaders;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/checkout/cancel', 'fr' => '/fr/checkout/cancel'], name: 'checkout_cancel', methods: ['GET'])]
final readonly class CheckoutCancelController
{
    public function __construct(
        private SalesQueries $sales,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->sales->order($request->query->getString('order')) ?? throw new NotFoundHttpException();

        $response = $this->page->render('site/checkout_cancel.html.twig', [
            'order' => $order,
            'back' => ProductType::from($order->productType)->section(),
        ]);
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);

        return $response;
    }
}
