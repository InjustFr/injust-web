<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Contact;

use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactMessageRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineContactMessageRepository implements ContactMessageRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(ContactMessage $message): void
    {
        $this->entityManager->persist($message);
    }

    public function get(Ulid $id): ContactMessage
    {
        return $this->entityManager->find(ContactMessage::class, $id) ?? throw new NotFound('message', $id->toBase32());
    }

    public function remove(ContactMessage $message): void
    {
        $this->entityManager->remove($message);
    }

    public function removeReceivedBefore(\DateTimeImmutable $before): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(ContactMessage::class, 'message')
            ->where('message.receivedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    public function newestFirst(bool $withHandled): array
    {
        return $this->entityManager->getRepository(ContactMessage::class)->findBy($withHandled ? [] : ['handledAt' => null], ['receivedAt' => 'DESC', 'id' => 'DESC']);
    }
}
