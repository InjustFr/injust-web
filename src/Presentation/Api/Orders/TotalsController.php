<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\SalesQueries;
use App\Application\Sales\Totals;
use App\Domain\Sales\FiscalYear;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/orders/totals', name: 'api_orders_totals', methods: ['GET'], format: 'json')]
final class TotalsController extends AbstractController
{
    public function __invoke(SalesQueries $queries, ClockInterface $clock, #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?int $year = null): JsonResponse
    {
        $totals = $queries->totals($year ?? FiscalYear::of($clock->now()));

        return $this->json([
            'byCountryAndType' => $totals->byCountryAndType,
            'euConsumerDigitalCrossBorderCents' => $totals->euConsumerDigitalCrossBorderCents,
            'euConsumerOnlineLessonsCents' => $totals->euConsumerOnlineLessonsCents,
            'euDigitalThresholdCents' => Totals::EU_DIGITAL_THRESHOLD_CENTS,
        ]);
    }
}
