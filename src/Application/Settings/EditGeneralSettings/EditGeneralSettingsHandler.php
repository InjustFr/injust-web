<?php

declare(strict_types=1);

namespace App\Application\Settings\EditGeneralSettings;

use App\Application\Settings\SettingsView;
use App\Application\Transaction;
use App\Domain\Settings\SettingsRepository;

final readonly class EditGeneralSettingsHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditGeneralSettings $command): SettingsView
    {
        $settings = $this->settings->current();
        $settings->editGeneral($command->contactEmail, $command->bookingUrl, $command->vatMentions, $command->seoTitle, $command->seoDescription, $command->searchIndexing);
        $this->settings->save($settings);
        $this->transaction->commit();

        return SettingsView::of($settings);
    }
}
