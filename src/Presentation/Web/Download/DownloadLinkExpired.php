<?php

declare(strict_types=1);

namespace App\Presentation\Web\Download;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class DownloadLinkExpired extends HttpException
{
    public function __construct(public readonly ?string $reference, \Throwable $previous)
    {
        parent::__construct(Response::HTTP_GONE, previous: $previous);
    }
}
