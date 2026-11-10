<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Beats;

use App\Domain\Beats\Deliverable;
use App\Domain\Beats\FileFormat;
use App\Domain\Beats\MusicalKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileFormatTest extends TestCase
{
    #[DataProvider('heads')]
    public function testAFormatIsRecognisedByItsMagicBytes(FileFormat $format, string $head, bool $matches): void
    {
        self::assertSame($matches, $format->matches($head));
    }

    /** @return iterable<string, array{FileFormat, string, bool}> */
    public static function heads(): iterable
    {
        yield 'mp3 with id3' => [FileFormat::Mp3, 'ID3'."\x04\x00", true];
        yield 'mp3 frame sync' => [FileFormat::Mp3, "\xFF\xFB\x90\x00", true];
        yield 'mp3 lowest frame sync' => [FileFormat::Mp3, "\xFF\xE0", true];
        yield 'mp3 broken sync' => [FileFormat::Mp3, "\xFF\xD0\x90\x00", false];
        yield 'mp3 no sync byte' => [FileFormat::Mp3, "\xFE\xFB\x90\x00", false];
        yield 'mp3 single byte' => [FileFormat::Mp3, "\xFF", false];
        yield 'mp3 empty' => [FileFormat::Mp3, '', false];
        yield 'mp3 id3 later' => [FileFormat::Mp3, 'xID3', false];
        yield 'mp3 frame sync later' => [FileFormat::Mp3, "x\xFF\xFB", false];
        yield 'wav' => [FileFormat::Wav, "RIFF\x24\x00\x00\x00WAVEfmt ", true];
        yield 'wav without wave' => [FileFormat::Wav, "RIFF\x24\x00\x00\x00AVI LIST", false];
        yield 'wav without riff' => [FileFormat::Wav, "RIFX\x24\x00\x00\x00WAVE", false];
        yield 'zip' => [FileFormat::Zip, "PK\x03\x04\x14\x00", true];
        yield 'empty zip' => [FileFormat::Zip, "PK\x05\x06", false];
        yield 'zip as mp3' => [FileFormat::Mp3, "PK\x03\x04", false];
    }

    public function testEachDeliverableHasItsFormat(): void
    {
        self::assertSame([FileFormat::Mp3, FileFormat::Wav, FileFormat::Zip], array_map(static fn (Deliverable $deliverable): FileFormat => $deliverable->format(), Deliverable::cases()));
    }

    public function testThereAreTwentyFourKeysWithReadableLabels(): void
    {
        self::assertSame('A minor', MusicalKey::AMinor->label());
        self::assertSame('C# major', MusicalKey::from('c-sharp-major')->label());
        self::assertSame('F# minor', MusicalKey::FSharpMinor->label());
        self::assertCount(24, array_unique(array_map(static fn (MusicalKey $key): string => $key->label(), MusicalKey::cases())));
    }
}
