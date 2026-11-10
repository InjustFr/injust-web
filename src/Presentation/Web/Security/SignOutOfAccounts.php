<?php

declare(strict_types=1);

namespace App\Presentation\Web\Security;

use App\Application\Identity\SingleSignOn;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class, priority: 128, dispatcher: 'security.event_dispatcher.main')]
final readonly class SignOutOfAccounts
{
    public function __construct(private SingleSignOn $singleSignOn)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new RedirectResponse($this->singleSignOn->signOutUrl()));
    }
}
