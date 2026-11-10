<?php

declare(strict_types=1);

namespace App\Presentation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class LocaleListener
{
    public const array LOCALES = ['en', 'fr'];

    public function __construct(private LocaleSwitcher $locales)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 30)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $locale = $request->getPreferredLanguage(self::LOCALES) ?? self::LOCALES[0];
        $request->setLocale($locale);
        $this->locales->setLocale($locale);
    }
}
