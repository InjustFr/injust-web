<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Sales;

use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineInvoiceRepository implements InvoiceRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Invoice $invoice): void
    {
        $this->entityManager->persist($invoice);
    }

    public function forOrder(Ulid $orderId): ?Invoice
    {
        return $this->entityManager->getRepository(Invoice::class)->findOneBy(['orderId' => $orderId, 'creditedInvoiceNumber' => null]);
    }
}
