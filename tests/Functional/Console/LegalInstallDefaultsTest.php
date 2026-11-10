<?php

declare(strict_types=1);

namespace App\Tests\Functional\Console;

use App\Application\Settings\SettingsQueries;
use App\Application\Transaction;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalDocumentRepository;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class LegalInstallDefaultsTest extends KernelTestCase
{
    public function testEveryDocumentIsCreatedFromItsDefaultMarkdown(): void
    {
        self::install()->assertCommandIsSuccessful();
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $queries = self::getContainer()->get(SettingsQueries::class);
        self::assertSame(LegalKind::cases(), array_map(static fn ($document) => $document->kind, $queries->legalDocuments()));
        $mentions = $queries->legalDocument(LegalKind::Mentions);
        self::assertSame(1, $mentions->version);
        self::assertStringContainsString('{siren}', $mentions->body->fr);
        self::assertStringContainsString('{siren}', $mentions->body->en);
    }

    public function testAnEditedDocumentIsLeftAsItIsAndTheOthersAreStillInstalled(): void
    {
        $container = self::getContainer();
        $container->get(LegalDocumentRepository::class)->add(LegalDocument::draft(LegalKind::Mentions, new LocalizedText('Mes mentions'), new \DateTimeImmutable()));
        $container->get(Transaction::class)->commit();

        self::install()->assertCommandIsSuccessful();
        self::install()->assertCommandIsSuccessful();

        $queries = $container->get(SettingsQueries::class);
        $mentions = $queries->legalDocument(LegalKind::Mentions);
        self::assertSame([1, 'Mes mentions'], [$mentions->version, $mentions->body->fr]);
        self::assertSame(1, $queries->legalDocument(LegalKind::Licence)->version);
    }

    private static function install(): CommandTester
    {
        $tester = new CommandTester(new Application(self::$kernel ?? self::bootKernel())->find('app:legal:install-defaults'));
        $tester->execute([]);

        return $tester;
    }
}
