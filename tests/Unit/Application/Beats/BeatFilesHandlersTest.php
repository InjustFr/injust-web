<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Beats\BeatView;
use App\Application\Beats\DeleteBeat\DeleteBeat;
use App\Application\Beats\DeleteBeat\DeleteBeatHandler;
use App\Application\Beats\RemoveBeatFile\RemoveBeatFile;
use App\Application\Beats\RemoveBeatFile\RemoveBeatFileHandler;
use App\Application\Beats\RemoveBeatPreview\RemoveBeatPreview;
use App\Application\Beats\RemoveBeatPreview\RemoveBeatPreviewHandler;
use App\Application\Beats\UploadBeatFile\UploadBeatFile;
use App\Application\Beats\UploadBeatFile\UploadBeatFileHandler;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreview;
use App\Application\Beats\UploadBeatPreview\UploadBeatPreviewHandler;
use App\Application\Beats\UploadCheck;
use App\Application\Shared\Images;
use App\Application\Shared\ImageStorage;
use App\Application\Transaction;
use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatDetails;
use App\Domain\Beats\BeatFile;
use App\Domain\Beats\BeatRepository;
use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\UnsupportedBeatFile;
use App\Domain\Beats\MusicalKey;
use App\Domain\Shared\ImageFocusRepository;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingTransaction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class BeatFilesHandlersTest extends TestCase
{
    private const string WAV = "RIFF\x24\x00\x00\x00WAVEfmt ";
    private const string MP3 = "\xFF\xFB\x90\x00";

    private Journal $journal;

    private Beat $beat;

    private BeatRepository $beats;

    private string $upload;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->beat = Beat::add(new BeatDetails('Riot', null, 140, MusicalKey::AMinor), 0);
        $beats = self::createStub(BeatRepository::class);
        $beats->method('get')->willReturn($this->beat);
        $beats->method('remove')->willReturnCallback(fn (): null => $this->journal->write('remove beat'));
        $this->beats = $beats;
        $this->upload = sys_get_temp_dir().'/beat-upload-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->upload);
    }

    public function testAFileIsStoredThenTheOneItReplacesIsDeletedAfterCommit(): void
    {
        $this->beat->useFile(Deliverable::Wav, new BeatFile('old', 'old.wav', 1, 'audio/wav'));

        $view = $this->uploadFile(new RecordingTransaction($this->journal), self::WAV);

        self::assertSame(['store private1 '.basename($this->upload).' as Riot.wav', 'commit', 'delete old'], $this->journal->entries);
        self::assertEquals(new BeatFile('private1', 'Riot.wav', \strlen(self::WAV), 'audio/x-wav'), $this->beat->file(Deliverable::Wav));
        self::assertSame(['originalName' => 'Riot.wav', 'size' => \strlen(self::WAV)], (array) $view->files['wav']);
    }

    public function testAFirstFileDeletesNothing(): void
    {
        $this->uploadFile(new RecordingTransaction($this->journal), self::WAV);

        self::assertSame(['store private1 '.basename($this->upload).' as Riot.wav', 'commit'], $this->journal->entries);
    }

    public function testAFailedCommitDeletesTheNewFileAndKeepsTheOldOne(): void
    {
        $this->beat->useFile(Deliverable::Wav, new BeatFile('old', 'old.wav', 1, 'audio/wav'));

        try {
            $this->uploadFile(new FailingTransaction($this->journal), self::WAV);
            self::fail('The commit failure was swallowed.');
        } catch (\RuntimeException $failure) {
            self::assertSame('database down', $failure->getMessage());
        }

        self::assertSame(['store private1 '.basename($this->upload).' as Riot.wav', 'commit failed', 'delete private1'], $this->journal->entries);
    }

    public function testAFileOfTheWrongFormatIsNeverStored(): void
    {
        try {
            $this->uploadFile(new RecordingTransaction($this->journal), self::MP3);
            self::fail('A WAV upload accepted an MP3.');
        } catch (UnsupportedBeatFile) {
        }

        self::assertSame([], $this->journal->entries);
        self::assertNull($this->beat->file(Deliverable::Wav));
    }

    public function testAPreviewIsStoredThenTheOneItReplacesIsDeletedAfterCommit(): void
    {
        $this->beat->usePreview(new BeatFile('old', 'old.mp3', 1, 'audio/mpeg'));

        $view = $this->uploadPreview(new RecordingTransaction($this->journal), self::MP3);

        self::assertSame(['store audio1', 'commit', 'delete old'], $this->journal->entries);
        self::assertEquals(new BeatFile('audio1', 'tag.mp3', \strlen(self::MP3), 'audio/mpeg'), $this->beat->preview());
        self::assertSame(['id' => 'audio1', 'originalName' => 'tag.mp3', 'size' => \strlen(self::MP3)], (array) $view->preview);
    }

    public function testAFirstPreviewDeletesNothing(): void
    {
        $this->uploadPreview(new RecordingTransaction($this->journal), self::MP3);

        self::assertSame(['store audio1', 'commit'], $this->journal->entries);
    }

    public function testAFailedPreviewCommitDeletesTheNewPreview(): void
    {
        try {
            $this->uploadPreview(new FailingTransaction($this->journal), self::MP3);
            self::fail('The commit failure was swallowed.');
        } catch (\RuntimeException $failure) {
            self::assertSame('database down', $failure->getMessage());
        }

        self::assertSame(['store audio1', 'commit failed', 'delete audio1'], $this->journal->entries);
    }

    public function testAPreviewThatIsNotAnMp3IsNeverStored(): void
    {
        try {
            $this->uploadPreview(new RecordingTransaction($this->journal), self::WAV);
            self::fail('A preview accepted a WAV.');
        } catch (UnsupportedBeatFile) {
        }

        self::assertSame([], $this->journal->entries);
    }

    public function testRemovedFilesAreDeletedAfterCommit(): void
    {
        $this->beat->useFile(Deliverable::Stems, new BeatFile('stems', 'stems.zip', 1, 'application/zip'));
        $this->beat->usePreview(new BeatFile('tag', 'tag.mp3', 1, 'audio/mpeg'));
        $journal = new Journal();
        $files = new RecordingPrivateFiles($journal);
        $audio = new RecordingAudio($journal);

        $removeFile = new RemoveBeatFileHandler($this->beats, $files, new RecordingTransaction($journal));
        self::assertNull($removeFile(new RemoveBeatFile($this->beat->id(), Deliverable::Stems))->files['stems']);
        $removeFile(new RemoveBeatFile($this->beat->id(), Deliverable::Stems));
        $removePreview = new RemoveBeatPreviewHandler($this->beats, $audio, new RecordingTransaction($journal));
        self::assertNull($removePreview(new RemoveBeatPreview($this->beat->id()))->preview);
        $removePreview(new RemoveBeatPreview($this->beat->id()));

        self::assertSame(['commit', 'delete stems', 'commit', 'commit', 'delete tag', 'commit'], $journal->entries);
    }

    public function testADeletedBeatTakesEveryFileWithItAfterCommit(): void
    {
        $this->beat->useFile(Deliverable::Mp3, new BeatFile('mp3', 'a.mp3', 1, 'audio/mpeg'));
        $this->beat->useFile(Deliverable::Stems, new BeatFile('stems', 'a.zip', 1, 'application/zip'));
        $this->beat->usePreview(new BeatFile('tag', 'tag.mp3', 1, 'audio/mpeg'));
        $this->beat->useCover('01HZZZZZZZZZZZZZZZZZZZZZZC');
        $images = self::createStub(ImageStorage::class);
        $images->method('delete')->willReturnCallback(fn (string $file): null => $this->journal->write('delete image '.$file));

        new DeleteBeatHandler($this->beats, new RecordingPrivateFiles($this->journal), new RecordingAudio($this->journal), new Images($images, self::createStub(ImageFocusRepository::class)), new RecordingTransaction($this->journal))(new DeleteBeat($this->beat->id()));

        self::assertSame(['remove beat', 'commit', 'delete mp3', 'delete stems', 'delete tag', 'delete image 01HZZZZZZZZZZZZZZZZZZZZZZC.webp'], $this->journal->entries);
    }

    public function testADeletedBeatWithoutFilesDeletesNothing(): void
    {
        $images = self::createStub(ImageStorage::class);
        $images->method('delete')->willReturnCallback(fn (string $file): null => $this->journal->write('delete image '.$file));

        new DeleteBeatHandler($this->beats, new RecordingPrivateFiles($this->journal), new RecordingAudio($this->journal), new Images($images, self::createStub(ImageFocusRepository::class)), new RecordingTransaction($this->journal))(new DeleteBeat($this->beat->id()));

        self::assertSame(['remove beat', 'commit'], $this->journal->entries);
    }

    private function uploadFile(Transaction $transaction, string $bytes): BeatView
    {
        new Filesystem()->dumpFile($this->upload, $bytes);
        $handler = new UploadBeatFileHandler($this->beats, new UploadCheck(['wav' => 100]), new RecordingPrivateFiles($this->journal), $transaction);

        return $handler(new UploadBeatFile($this->beat->id(), Deliverable::Wav, $this->upload, 'Riot.wav'));
    }

    private function uploadPreview(Transaction $transaction, string $bytes): BeatView
    {
        new Filesystem()->dumpFile($this->upload, $bytes);
        $handler = new UploadBeatPreviewHandler($this->beats, new UploadCheck(['preview' => 100]), new RecordingAudio($this->journal), $transaction);

        return $handler(new UploadBeatPreview($this->beat->id(), $this->upload, 'tag.mp3'));
    }
}
