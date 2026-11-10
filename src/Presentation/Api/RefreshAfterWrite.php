<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use App\Presentation\ApiPreload;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
final readonly class RefreshAfterWrite
{
    public const string REQUEST_HEADER = 'X-Refresh';
    public const string RESPONSE_HEADER = 'X-Refreshed';

    private const int MAX_URLS = 12;

    public function __construct(private ApiPreload $preload)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $urls = $this->requestedUrls($request);
        $content = Response::HTTP_NO_CONTENT === $response->getStatusCode() ? 'null' : $response->getContent();

        if (!$event->isMainRequest() || $request->isMethodSafe() || !$response->isSuccessful() || [] === $urls || !\is_string($content) || !json_validate($content)) {
            return;
        }

        $response->setContent('{"data":'.$content.',"refreshed":'.$this->preload->json($urls).'}');
        $response->setStatusCode(Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set(self::RESPONSE_HEADER, '1');
    }

    /**
     * @return list<string>
     */
    private function requestedUrls(Request $request): array
    {
        $header = $request->headers->get(self::REQUEST_HEADER);
        $urls = null === $header ? null : json_decode($header, true);
        if (!\is_array($urls)) {
            return [];
        }

        return \array_slice(array_values(array_filter($urls, static fn (mixed $url): bool => \is_string($url) && str_starts_with($url, '/api/'))), 0, self::MAX_URLS);
    }
}
