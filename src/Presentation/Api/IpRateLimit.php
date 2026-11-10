<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class IpRateLimit
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function refuse(RateLimiterFactoryInterface $limiter, Request $request, string $message): ?Response
    {
        if ($limiter->create(IpUtils::anonymize($request->getClientIp() ?? '0.0.0.0', 0, 8))->consume()->isAccepted()) {
            return null;
        }

        return new ProblemResponse(Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS], Response::HTTP_TOO_MANY_REQUESTS, $this->translator->trans($message, [], 'exceptions'));
    }
}
