<?php

declare(strict_types=1);

namespace App\Application\Settings\UploadSiteImage;

use App\Application\Settings\SettingsView;
use App\Application\Shared\ImageResizer;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\SiteImage;

final readonly class UploadSiteImageHandler
{
    private const int HERO_SIDE = 3200;

    public function __construct(
        private SettingsRepository $settings,
        private ImageResizer $resizer,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(UploadSiteImage $command): SettingsView
    {
        $image = $this->images->store($this->resizer->resize($command->path, SiteImage::Hero === $command->slot ? self::HERO_SIDE : ImageResizer::MAX_SIDE));

        $settings = $this->settings->current();
        $previous = $settings->useImage($command->slot, $image);
        $this->settings->save($settings);
        $this->transaction->commit();
        $this->images->discard($previous);

        return SettingsView::of($settings);
    }
}
