<?php

declare(strict_types=1);

namespace App\Application\Settings\EditLegalIdentity;

use App\Application\Settings\SettingsView;
use App\Application\Transaction;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\SettingsRepository;

final readonly class EditLegalIdentityHandler
{
    public function __construct(
        private SettingsRepository $settings,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLegalIdentity $command): SettingsView
    {
        $settings = $this->settings->current();
        $settings->editLegalIdentity(new LegalIdentity(
            $command->name,
            $command->businessForm,
            $command->siren,
            $command->address,
            $command->email,
            $command->phone,
            $command->publicationDirector,
            $command->mediatorName,
            $command->mediatorUrl,
            $command->hostName,
            $command->hostAddress,
            $command->hostPhone,
        ));
        $this->settings->save($settings);
        $this->transaction->commit();

        return SettingsView::of($settings);
    }
}
