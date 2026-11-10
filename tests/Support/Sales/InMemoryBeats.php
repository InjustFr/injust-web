<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatRepository;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\Uid\Ulid;

final class InMemoryBeats implements BeatRepository
{
    /** @var array<string, Beat> */
    public array $beats = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function add(Beat $beat): void
    {
        $this->beats[$beat->id()->toBase32()] = $beat;
    }

    public function get(Ulid $id): Beat
    {
        return $this->find($id) ?? throw new NotFound('beat', $id->toBase32());
    }

    public function find(Ulid $id): ?Beat
    {
        return $this->beats[$id->toBase32()] ?? null;
    }

    public function lock(Ulid $id): ?Beat
    {
        $this->journal->write('lock beat '.$id->toBase32());

        return $this->find($id);
    }

    public function remove(Beat $beat): void
    {
        unset($this->beats[$beat->id()->toBase32()]);
    }

    public function all(): array
    {
        return array_values($this->beats);
    }

    public function slugTaken(string $slug, ?Ulid $except): bool
    {
        return false;
    }
}
