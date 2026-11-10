<?php

declare(strict_types=1);

namespace App\Application\Settings\SwitchOnlineCheckout;

use App\Application\Settings\SettingsView;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;

final readonly class SwitchOnlineCheckoutHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SwitchOnlineCheckout $command): SettingsView
    {
        $settings = $this->settings->current();
        $command->enabled ? $settings->enableOnlineCheckout() : $settings->disableOnlineCheckout();
        $this->settings->save($settings);
        $this->transaction->commit();

        return SettingsView::of($settings);
    }
}
