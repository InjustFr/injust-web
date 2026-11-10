<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Exception\CheckoutClosed;
use App\Domain\Sales\Exception\MissingConsent;
use App\Domain\Sales\Exception\OrderNotFound;
use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\Exception\PaymentMismatch;
use App\Domain\Sales\Exception\PaymentUnavailable;
use App\Domain\Sales\Exception\ProductUnavailable;
use App\Domain\Sales\Exception\SectionClosed;
use App\Domain\Sales\Exception\TermsChanged;
use App\Domain\Shared\Exception\DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SalesExceptionsTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): DomainException, string, array<string, string>}> */
    public static function exceptions(): iterable
    {
        yield 'checkout closed' => [static fn (): DomainException => new CheckoutClosed(), 'sales.checkout_closed', []];
        yield 'section closed' => [static fn (): DomainException => new SectionClosed(), 'sales.section_closed', []];
        yield 'product unavailable' => [static fn (): DomainException => new ProductUnavailable(), 'sales.product_unavailable', []];
        yield 'missing consent' => [static fn (): DomainException => new MissingConsent(), 'sales.missing_consent', []];
        yield 'terms changed' => [static fn (): DomainException => new TermsChanged(), 'sales.terms_changed', []];
        yield 'payment unavailable' => [static fn (): DomainException => new PaymentUnavailable(), 'sales.payment_unavailable', []];
        yield 'payment mismatch' => [static fn (): DomainException => new PaymentMismatch('INJ-AAAAAA'), 'sales.payment_mismatch', ['reference' => 'INJ-AAAAAA']];
        yield 'order not paid' => [static fn (): DomainException => new OrderNotPaid('INJ-AAAAAA'), 'sales.order_not_paid', ['reference' => 'INJ-AAAAAA']];
        yield 'order not found' => [static fn (): DomainException => new OrderNotFound('payment', 'pi_1'), 'sales.order_not_found', ['by' => 'payment', 'id' => 'pi_1']];
    }

    /**
     * @param \Closure(): DomainException $raise
     * @param array<string, string>       $parameters
     */
    #[DataProvider('exceptions')]
    public function testEachRefusalCarriesItsTranslationKeyAndParameters(\Closure $raise, string $key, array $parameters): void
    {
        $exception = $raise();

        self::assertSame([$key, $parameters], [$exception->getMessage(), $exception->parameters()]);
    }
}
