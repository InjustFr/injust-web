<?php

declare(strict_types=1);

namespace App\Presentation\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ProblemResponse extends JsonResponse
{
    public function __construct(string $title, int $status, string $detail)
    {
        parent::__construct(['title' => $title, 'status' => $status, 'detail' => $detail], $status, ['Content-Type' => 'application/problem+json']);
    }
}
