<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Settings\EditGeneralSettings\EditGeneralSettings;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettingsHandler;
use App\Application\Settings\EditLegalDocument\EditLegalDocument;
use App\Application\Settings\EditLegalDocument\EditLegalDocumentHandler;
use App\Application\Settings\EditLegalIdentity\EditLegalIdentity;
use App\Application\Settings\EditLegalIdentity\EditLegalIdentityHandler;
use App\Application\Settings\SwitchSections\SwitchSections;
use App\Application\Settings\SwitchSections\SwitchSectionsHandler;
use App\Application\Settings\UploadSiteImage\UploadSiteImage;
use App\Application\Settings\UploadSiteImage\UploadSiteImageHandler;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\SiteImage;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;

trait ConfiguresSite
{
    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        new Filesystem()->remove(self::$kernel?->getShareDir() ?? []);
        parent::tearDown();
    }

    private static function switchSections(bool $lessons, bool $production, bool $beats): void
    {
        self::getContainer()->get(SwitchSectionsHandler::class)(new SwitchSections($lessons, $production, $beats));
    }

    private static function editGeneral(bool $searchIndexing = false, string $contactEmail = 'hello@injust.test'): void
    {
        self::getContainer()->get(EditGeneralSettingsHandler::class)(new EditGeneralSettings(
            $contactEmail,
            null,
            new VatMentions('TVA non applicable – art. 293 B du CGI', 'Autoliquidation', 'Hors UE'),
            new LocalizedText('Injust — punk de protestation', 'Injust — protest punk'),
            new LocalizedText('Punk de protestation.', 'Protest punk.'),
            $searchIndexing,
        ));
    }

    private static function editIdentity(string $name = 'Louis Injust'): void
    {
        self::getContainer()->get(EditLegalIdentityHandler::class)(new EditLegalIdentity(
            $name,
            'EI',
            '443 061 841',
            '1 rue du Son, 75000 Paris',
            'louis@injust.test',
            '+33 6 00 00 00 00',
            'Louis Injust',
            'Médiateur Test',
            'https://mediator.test',
            'OVH SAS',
            '2 rue Kellermann, 59100 Roubaix',
            '+33 9 72 10 10 07',
        ));
    }

    private static function writeLegal(LegalKind $kind, string $fr, string $en = ''): void
    {
        self::getContainer()->get(EditLegalDocumentHandler::class)(new EditLegalDocument($kind, new LocalizedText($fr, $en)));
    }

    private static function upload(SiteImage $slot): void
    {
        self::getContainer()->get(UploadSiteImageHandler::class)(new UploadSiteImage($slot, \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));
    }
}
