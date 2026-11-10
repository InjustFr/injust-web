<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Sales\DownloadLinks;
use App\Application\Sales\SalesQueries;
use App\Domain\Sales\OrderStatus;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route(path: ['en' => '/checkout/success', 'fr' => '/fr/checkout/success'], name: 'checkout_success', methods: ['GET'])]
final readonly class CheckoutSuccessController
{
    public function __construct(
        private SalesQueries $sales,
        private DownloadLinks $links,
        private SitePage $page,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->sales->order($request->query->getString('order')) ?? throw new NotFoundHttpException();

        $paid = null !== $order->licence && OrderStatus::Paid->value === $order->status;
        $fresh = $paid && $order->paidAt > $this->clock->now()->sub(new \DateInterval(DownloadLinks::VALIDITY));

        return $this->page->render('site/checkout_success.html.twig', [
            'order' => $order,
            'downloads' => $fresh ? [...$this->links->forOrder($order), $this->links->licence($order)] : [],
            'downloads_expired' => $paid && !$fresh,
        ]);
    }
}
