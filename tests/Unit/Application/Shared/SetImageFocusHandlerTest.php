<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared;

use App\Application\Shared\ImageStorage;
use App\Application\Shared\SetImageFocus\SetImageFocus;
use App\Application\Shared\SetImageFocus\SetImageFocusHandler;
use App\Domain\Shared\Exception\InvalidFocus;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\ImageFocus;
use App\Domain\Shared\ImageFocusRepository;
use App\Tests\Support\Sales\Journal;
use App\Tests\Support\Sales\RecordingTransaction;
use PHPUnit\Framework\TestCase;

final class SetImageFocusHandlerTest extends TestCase
{
    private const string IMAGE = '01HZZZZZZZZZZZZZZZZZZZZZZC';

    private Journal $journal;

    private ImageFocus $focus;

    protected function setUp(): void
    {
        $this->journal = new Journal();
        $this->focus = ImageFocus::centered(self::IMAGE);
    }

    public function testAFocusIsSavedThenCommitted(): void
    {
        $view = $this->handler()(new SetImageFocus(self::IMAGE, 35, 60));

        self::assertSame([self::IMAGE, 35, 60], [$view->id, $view->x, $view->y]);
        self::assertSame([35, 60], [$this->focus->x(), $this->focus->y()]);
        self::assertSame(['save '.self::IMAGE, 'commit'], $this->journal->entries);
    }

    public function testACenteredFocusRemovesTheRow(): void
    {
        $this->focus->moveTo(10, 10);

        $view = $this->handler()(new SetImageFocus(self::IMAGE, 50, 50));

        self::assertSame([50, 50], [$view->x, $view->y]);
        self::assertSame(['remove '.self::IMAGE, 'commit'], $this->journal->entries);
    }

    public function testAnUnknownImageIsNotFound(): void
    {
        try {
            $this->handler(null)(new SetImageFocus(self::IMAGE, 35, 60));
            self::fail('The focus was set.');
        } catch (NotFound $missing) {
            self::assertSame(['subject' => 'image', 'id' => self::IMAGE], $missing->parameters());
        }
        self::assertSame([], $this->journal->entries);
    }

    public function testAnInvalidFocusChangesNothing(): void
    {
        $this->expectException(InvalidFocus::class);

        try {
            $this->handler()(new SetImageFocus(self::IMAGE, 33, 60));
        } finally {
            self::assertSame([], $this->journal->entries);
        }
    }

    private function handler(?string $path = '/share/images/x.webp'): SetImageFocusHandler
    {
        $storage = self::createStub(ImageStorage::class);
        $storage->method('path')->willReturnCallback(static fn (string $file): ?string => self::IMAGE.'.webp' === $file ? $path : null);
        $focuses = self::createStub(ImageFocusRepository::class);
        $focuses->method('get')->willReturnCallback(fn (string $image): ImageFocus => self::IMAGE === $image ? $this->focus : ImageFocus::centered($image));
        $focuses->method('save')->willReturnCallback(fn (ImageFocus $focus): null => $this->journal->write('save '.$focus->image()));
        $focuses->method('remove')->willReturnCallback(fn (ImageFocus $focus): null => $this->journal->write('remove '.$focus->image()));

        return new SetImageFocusHandler($focuses, $storage, new RecordingTransaction($this->journal));
    }
}
