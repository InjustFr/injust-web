<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface SettingsRepository
{
    public function current(): Settings;

    public function save(Settings $settings): void;
}
