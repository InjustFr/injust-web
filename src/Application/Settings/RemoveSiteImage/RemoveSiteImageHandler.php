<?php

declare(strict_types=1);

namespace App\Application\Settings\RemoveSiteImage;

use App\Application\Settings\SettingsView;
use App\Application\Shared\Images;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;

final readonly class RemoveSiteImageHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private Images $images,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(RemoveSiteImage $command): SettingsView
    {
        $settings = $this->settings->current();
        $previous = $settings->removeImage($command->slot);
        $this->transaction->commit();
        $this->images->discard($previous);

        return SettingsView::of($settings);
    }
}
