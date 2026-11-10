<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ErrorPageLocale
{
    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_route')) {
            return;
        }

        $request->setLocale(str_starts_with($request->getPathInfo().'/', '/fr/') ? 'fr' : 'en');
    }
}
