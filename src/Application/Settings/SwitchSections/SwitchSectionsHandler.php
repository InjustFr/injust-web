<?php

declare(strict_types=1);

namespace App\Application\Settings\SwitchSections;

use App\Application\Settings\SettingsView;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;

final readonly class SwitchSectionsHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SwitchSections $command): SettingsView
    {
        $settings = $this->settings->current();
        $settings->switchSections($command->lessons, $command->production, $command->beats);
        $this->settings->save($settings);
        $this->transaction->commit();

        return SettingsView::of($settings);
    }
}
