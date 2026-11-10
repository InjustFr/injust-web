<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\FakeCheckout;

use App\Presentation\Web\FakeCheckout\PayFakeCheckoutController;
use App\Presentation\Web\FakeCheckout\ShowFakeCheckoutController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

final class FakeCheckoutRoutesTest extends TestCase
{
    /** @param class-string $controller */
    #[DataProvider('controllers')]
    public function testTheFakeCheckoutOnlyExistsInDevAndTest(string $controller): void
    {
        self::assertCount(0, new AttributeRouteControllerLoader('prod')->load($controller));
        self::assertCount(1, new AttributeRouteControllerLoader('dev')->load($controller));
        self::assertCount(1, new AttributeRouteControllerLoader('test')->load($controller));
    }

    /** @return iterable<string, array{class-string}> */
    public static function controllers(): iterable
    {
        yield 'page' => [ShowFakeCheckoutController::class];
        yield 'pay' => [PayFakeCheckoutController::class];
    }
}
