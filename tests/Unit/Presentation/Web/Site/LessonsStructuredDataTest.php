<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Lessons\LessonOfferView;
use App\Domain\Lessons\LessonFormat;
use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferDetails;
use App\Domain\Lessons\OfferKind;
use App\Domain\Shared\LocalizedText;
use App\Domain\Shared\Price;
use App\Presentation\Web\Site\LessonsStructuredData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\IdentityTranslator;

final class LessonsStructuredDataTest extends TestCase
{
    public function testTheServiceListsEveryOfferWithItsPrice(): void
    {
        $offers = [
            self::offer(new LocalizedText('Cours', 'Lesson'), new LocalizedText('Une heure.', 'One hour.'), 4550),
            self::offer(new LocalizedText('Essai', 'Trial'), new LocalizedText(), 5),
            self::offer(new LocalizedText('Pack'), new LocalizedText(), 25010),
        ];

        $service = new LessonsStructuredData(self::urls(), new IdentityTranslator())->service($offers, 'fr');

        self::assertSame([
            '@type' => 'Service',
            '@id' => 'https://injust.test/lessons#service',
            'name' => 'site.lessons.service',
            'serviceType' => 'site.lessons.service',
            'url' => 'https://injust.test/lessons',
            'provider' => ['@id' => 'https://injust.test/home#band'],
            'offers' => [
                ['@type' => 'Offer', 'name' => 'Cours', 'description' => 'Une heure.', 'price' => '45.50', 'priceCurrency' => 'EUR', 'url' => 'https://injust.test/lessons'],
                ['@type' => 'Offer', 'name' => 'Essai', 'price' => '0.05', 'priceCurrency' => 'EUR', 'url' => 'https://injust.test/lessons'],
                ['@type' => 'Offer', 'name' => 'Pack', 'price' => '250.10', 'priceCurrency' => 'EUR', 'url' => 'https://injust.test/lessons'],
            ],
        ], $service);
    }

    private static function offer(LocalizedText $title, LocalizedText $description, int $cents): LessonOfferView
    {
        return LessonOfferView::of(LessonOffer::add(new LessonOfferDetails($title, $description, OfferKind::Single, 1, LessonFormat::Online, 60, new Price($cents)), 0));
    }

    private static function urls(): UrlGeneratorInterface
    {
        return new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return self::ABSOLUTE_URL === $referenceType && [] === $parameters ? 'https://injust.test/'.$name : 'wrong';
            }
        };
    }
}
