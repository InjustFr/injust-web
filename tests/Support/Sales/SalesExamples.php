<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\Consent;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Order;
use App\Domain\Sales\PaymentDetails;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Sales\Terms;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;

final class SalesExamples
{
    public static function lesson(int $cents = 4500, ?string $format = 'online', string $id = '01JCLESSON0000000000000000'): Purchasable
    {
        return new Purchasable(ProductType::Lesson, $id, 'Cours de guitare', new Price($cents), $format);
    }

    public static function production(int $cents = 12000, string $id = '01JCSERVICE000000000000000'): Purchasable
    {
        return new Purchasable(ProductType::Production, $id, 'Mixage', new Price($cents));
    }

    public static function beat(int $cents = 3000): Purchasable
    {
        return new Purchasable(ProductType::Beat, '01JCBEAT000000000000000000', 'Riot beat', new Price($cents));
    }

    /** @return list<Consent> */
    public static function consents(ProductType $type, string $at = '2026-11-10 10:00:00'): array
    {
        return array_map(static fn ($kind): Consent => new Consent($kind, 'Texte '.$kind->value, new \DateTimeImmutable($at)), $type->requiredConsents());
    }

    public static function order(?Purchasable $product = null, string $at = '2026-11-10 10:00:00', string $locale = 'fr', string $email = 'buyer@example.test'): Order
    {
        $product ??= self::lesson();

        return Order::place($product, $locale, $email, self::consents($product->type, $at), self::terms(), new \DateTimeImmutable($at));
    }

    public static function terms(int $version = 3): Terms
    {
        return new Terms($version, new LocalizedText('CGV v'.$version, 'Terms v'.$version), new \DateTimeImmutable('2026-11-01 09:00:00 UTC'));
    }

    public static function customer(string $country = 'FR', ?string $vatId = null, ?string $company = null, string $name = 'Test Buyer', ?string $cardCountry = 'FR'): Customer
    {
        return new Customer($name, 'stripe@example.test', $country, '1 rue du Son', '', '75000', 'Paris', null, $company, $vatId, $cardCountry);
    }

    public static function payment(Order $order, ?Customer $customer = null, string $paidAt = '2026-11-10 10:05:00', ?int $cents = null, string $currency = 'eur'): PaymentDetails
    {
        return new PaymentDetails($order->checkoutSessionId() ?? 'cs_test', 'pi_'.$order->reference(), $cents ?? $order->price()->cents, $currency, $customer ?? self::customer(), new \DateTimeImmutable($paidAt));
    }

    public static function paid(?Purchasable $product = null, ?Customer $customer = null, string $paidAt = '2026-11-10 10:05:00', string $session = 'cs_test'): Order
    {
        $order = self::order($product, $paidAt);
        $order->attachSession($session);
        $order->pay(self::payment($order, $customer, $paidAt));

        return $order;
    }
}
