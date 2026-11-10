<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use App\Presentation\Web\Security\SecurityHeaders;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class PrivateResponses
{
    private const array ROUTES = ['download', 'download_licence', 'checkout_success'];

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !\in_array($event->getRequest()->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }
        $response = $event->getResponse();
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);
    }
}
