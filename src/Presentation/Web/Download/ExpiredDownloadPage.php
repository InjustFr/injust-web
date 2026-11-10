<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use App\Presentation\Web\Site\SitePage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ExpiredDownloadPage
{
    public function __construct(private SitePage $page)
    {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function __invoke(ExceptionEvent $event): void
    {
        $expired = $event->getThrowable();
        if (!$expired instanceof DownloadLinkExpired) {
            return;
        }
        $event->setResponse($this->page->render('site/download_expired.html.twig', ['reference' => $expired->reference]));
    }
}
