<?php

declare(strict_types=1);

namespace App\Presentation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final readonly class ApiPreload
{
    public function __construct(
        private RequestStack $requests,
        private HttpKernelInterface $kernel,
    ) {
    }

    /**
     * @param list<string> $urls
     */
    public function json(array $urls): string
    {
        $page = $this->requests->getMainRequest();
        if (null === $page) {
            return '{}';
        }

        $entries = [];
        foreach ($urls as $url) {
            $response = $this->kernel->handle($this->apiRequest($page, $url), HttpKernelInterface::SUB_REQUEST);
            $content = $response->getContent();
            if (Response::HTTP_OK === $response->getStatusCode() && \is_string($content) && json_validate($content)) {
                $entries[] = json_encode($url, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES).':'.str_replace('<', '\u003C', $content);
            }
        }

        return '{'.implode(',', $entries).'}';
    }

    private function apiRequest(Request $page, string $url): Request
    {
        $request = Request::create($url, server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_HOST' => $page->getHttpHost()]);
        $request->cookies->add($page->cookies->all());
        if ($page->hasSession()) {
            $request->setSession($page->getSession());
        }
        $request->setLocale($page->getLocale());

        return $request;
    }
}
