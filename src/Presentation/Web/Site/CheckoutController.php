<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Application\Sales\CheckoutProducts;
use App\Application\Settings\SettingsQueries;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\Exception\NotFound;
use App\Presentation\Web\Security\SecurityHeaders;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\EnumRequirement;

#[AsController]
#[Route(path: ['en' => '/checkout/{productType}/{productId}', 'fr' => '/fr/checkout/{productType}/{productId}'], name: 'checkout', requirements: ['productType' => new EnumRequirement(ProductType::class)], methods: ['GET'])]
final readonly class CheckoutController
{
    public function __construct(
        private SettingsQueries $settings,
        private CheckoutProducts $products,
        private LessonsQueries $lessons,
        private ProductionQueries $production,
        private PriceFormat $prices,
        private SitePage $page,
    ) {
    }

    public function __invoke(Request $request, ProductType $productType, string $productId): Response
    {
        $locale = $request->getLocale();
        $product = $this->sellable($productType, $productId, $locale);
        try {
            $cgv = $this->settings->legalDocument($productType->cgvKind());
        } catch (NotFound $notFound) {
            throw new NotFoundHttpException(previous: $notFound);
        }

        $response = $this->page->render('site/checkout.html.twig', [
            'section' => $productType->section(),
            'product' => $product,
            'price' => $this->prices->format($product->price->cents, $locale),
            'lesson' => ProductType::Lesson === $productType ? $this->lessons->offer($productId) : null,
            'service' => ProductType::Production === $productType ? $this->production->service($productId) : null,
            'cgv' => $cgv,
        ]);
        $response->headers->set('X-Robots-Tag', SecurityHeaders::NOINDEX);

        return $response;
    }

    private function sellable(ProductType $type, string $id, string $locale): Purchasable
    {
        $settings = $this->settings->current();
        $product = $settings->onlineCheckout && $settings->isEnabled($type->section()) ? $this->products->find($type, $id, $locale) : null;

        return $product ?? throw new NotFoundHttpException();
    }
}
