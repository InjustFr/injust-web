<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface DownloadLinks
{
    public const string VALIDITY = 'P7D';
    public const string LICENCE = 'licence';

    /** @return list<DownloadLink> */
    public function forOrder(OrderView $order): array;

    public function licence(OrderView $order): DownloadLink;
}
