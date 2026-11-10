<?php

declare(strict_types=1);

namespace App\Application\Settings\UploadSiteImage;

use App\Application\Settings\SettingsView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\ImageStorage;
use App\Application\Shared\ResizedImage;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\Uid\Ulid;

final readonly class UploadSiteImageHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private ImageResizer $resizer,
        private ImageStorage $storage,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadSiteImage $command): SettingsView
    {
        $id = new Ulid();
        $this->storage->store($id, $this->resizer->resize($command->path));

        $settings = $this->settings->current();
        $previous = $settings->useImage($command->slot, $id->toBase32());
        $this->settings->save($settings);
        $this->transaction->commit();
        if (null !== $previous) {
            $this->storage->delete($previous.'.'.ResizedImage::EXTENSION);
        }

        return SettingsView::of($settings);
    }
}
