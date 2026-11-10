<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Beats\UploadCheck;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\BeatFileTooLarge;
use App\Domain\Beats\Exception\UnsupportedBeatFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class UploadCheckTest extends TestCase
{
    private const string MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00";
    private const string WAV = "RIFF\x24\x00\x00\x00WAVEfmt ";
    private const string ZIP = "PK\x03\x04\x14\x00\x00\x00";

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/upload-check-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testFilesOfTheRightFormatWithinTheLimitPass(): void
    {
        $check = new UploadCheck(['preview' => 10, 'mp3' => 10, 'wav' => 16, 'stems' => 8]);

        self::assertSame(\strlen(self::MP3), $check->preview($this->file(self::MP3)));
        $check->deliverable($this->file(self::MP3), Deliverable::Mp3);
        $check->deliverable($this->file(self::WAV), Deliverable::Wav);
        $check->deliverable($this->file(self::ZIP), Deliverable::Stems);

        $this->addToAssertionCount(3);
    }

    public function testAPreviewMustBeAnMp3(): void
    {
        self::assertEquals(new UnsupportedBeatFile('mp3'), self::failure(fn () => new UploadCheck(['preview' => 100])->preview($this->file(self::WAV))));
    }

    public function testADeliverableMustMatchItsFormat(): void
    {
        self::assertEquals(new UnsupportedBeatFile('zip'), self::failure(fn () => new UploadCheck(['stems' => 100])->deliverable($this->file(self::WAV), Deliverable::Stems)));
        self::assertEquals(new UnsupportedBeatFile('wav'), self::failure(fn () => new UploadCheck(['wav' => 100])->deliverable($this->file(self::MP3), Deliverable::Wav)));
    }

    public function testAnEmptyOrMissingFileIsRefused(): void
    {
        $check = new UploadCheck(['wav' => 100]);

        self::assertInstanceOf(UnsupportedBeatFile::class, self::failure(fn () => $check->deliverable($this->file(''), Deliverable::Wav)));
        self::assertInstanceOf(UnsupportedBeatFile::class, self::failure(fn () => $check->deliverable($this->directory.'/missing', Deliverable::Wav)));
    }

    public function testAFileOverTheConfiguredLimitIsRefused(): void
    {
        self::assertEquals(new BeatFileTooLarge(1), self::failure(fn () => new UploadCheck(['preview' => 100, 'mp3' => 9])->deliverable($this->file(self::MP3), Deliverable::Mp3)));
    }

    public function testTheLimitIsGivenToTheMessage(): void
    {
        self::assertSame(['megabytes' => 2], new BeatFileTooLarge(2)->parameters());
        self::assertSame(['format' => 'wav'], new UnsupportedBeatFile('wav')->parameters());
    }

    public function testAPreviewOverItsLimitIsRefused(): void
    {
        self::assertInstanceOf(BeatFileTooLarge::class, self::failure(fn () => new UploadCheck(['preview' => 9, 'mp3' => 100])->preview($this->file(self::MP3))));
    }

    public function testTheLimitItselfIsAllowed(): void
    {
        new UploadCheck(['mp3' => \strlen(self::MP3)])->deliverable($this->file(self::MP3), Deliverable::Mp3);

        $this->addToAssertionCount(1);
    }

    public function testTheLimitIsReportedInMegabytesRoundedUp(): void
    {
        $megabyte = 1024 * 1024;

        self::assertEquals(new BeatFileTooLarge(2), self::failure(fn () => new UploadCheck(['wav' => $megabyte + 1])->deliverable($this->file(str_pad(self::WAV, $megabyte + 2, "\0")), Deliverable::Wav)));
    }

    private function file(string $bytes): string
    {
        $path = $this->directory.'/'.bin2hex(random_bytes(4));
        new Filesystem()->dumpFile($path, $bytes);

        return $path;
    }

    private static function failure(callable $call): ?\Throwable
    {
        try {
            $call();
        } catch (\Throwable $failure) {
            return $failure;
        }

        return null;
    }
}
