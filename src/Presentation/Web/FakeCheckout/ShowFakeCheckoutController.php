<?php

declare(strict_types=1);

namespace App\Presentation\Web\FakeCheckout;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/_fake-checkout/{session}', name: 'fake_checkout', requirements: ['session' => 'cs_[A-Za-z0-9_]+'], methods: ['GET'], env: ['dev', 'test'])]
final class ShowFakeCheckoutController extends AbstractController
{
    public function __invoke(string $session, HttpClientInterface $httpClient): Response
    {
        $checkout = $httpClient->request('GET', 'https://api.stripe.com/v1/checkout/sessions/'.$session);
        if (Response::HTTP_OK !== $checkout->getStatusCode()) {
            throw $this->createNotFoundException();
        }

        return $this->render('fake_checkout/show.html.twig', ['session' => $session, 'checkout' => $checkout->toArray()]);
    }
}
