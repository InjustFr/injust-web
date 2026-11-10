<?php

declare(strict_types=1);

namespace App\Domain\Beats;

final readonly class BeatFile
{
    public const int MAX_NAME = 200;

    public string $originalName;

    public function __construct(
        public string $id,
        string $originalName,
        public int $size,
        public string $mimeType,
    ) {
        $this->originalName = mb_substr(trim($originalName), 0, self::MAX_NAME);
    }

    /** @param array{id: string, originalName: string, size: int, mimeType: string} $stored */
    public static function fromArray(array $stored): self
    {
        return new self($stored['id'], $stored['originalName'], $stored['size'], $stored['mimeType']);
    }

    /** @return array{id: string, originalName: string, size: int, mimeType: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'originalName' => $this->originalName, 'size' => $this->size, 'mimeType' => $this->mimeType];
    }
}
