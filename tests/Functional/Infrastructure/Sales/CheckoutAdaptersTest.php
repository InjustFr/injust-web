<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Sales;

use App\Application\Sales\CheckoutUrls;
use App\Application\Sales\ConsentTexts;
use App\Domain\Sales\ConsentKind;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CheckoutAdaptersTest extends KernelTestCase
{
    public function testTheConsentSentenceIsInTheOrderLocaleWithTheCgvVersion(): void
    {
        $texts = self::getContainer()->get(ConsentTexts::class);

        self::assertSame('J’ai lu et j’accepte les conditions générales de vente (version 3).', $texts->text(ConsentKind::Terms, 'fr', 3));
        self::assertSame('I have read and accept the terms of sale (version 3).', $texts->text(ConsentKind::Terms, 'en', 3));
    }

    public function testTheReturnUrlsAreAbsoluteAndLocalized(): void
    {
        $urls = self::getContainer()->get(CheckoutUrls::class);

        self::assertSame('http://localhost/checkout/success?order=abc', $urls->success('abc', 'en'));
        self::assertSame('http://localhost/fr/checkout/success?order=abc', $urls->success('abc', 'fr'));
        self::assertSame('http://localhost/checkout/cancel?order=abc', $urls->cancel('abc', 'en'));
        self::assertSame('http://localhost/fr/checkout/cancel?order=abc', $urls->cancel('abc', 'fr'));
    }
}
