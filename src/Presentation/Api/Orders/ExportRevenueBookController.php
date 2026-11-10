<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\RevenueLine;
use App\Application\Sales\SalesQueries;
use App\Domain\Sales\FiscalYear;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/orders/export', name: 'api_orders_export', methods: ['GET'])]
final class ExportRevenueBookController extends AbstractController
{
    public function __invoke(SalesQueries $queries, ClockInterface $clock, #[MapQueryParameter(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] ?int $year = null): Response
    {
        $year ??= FiscalYear::of($clock->now());
        $file = fopen('php://temp', 'r+');
        \assert(false !== $file);
        fwrite($file, "\xEF\xBB\xBF");
        self::put($file, ['date', 'invoice', 'reference', 'customer', 'country', 'type', 'amount', 'payment']);
        foreach ($queries->revenueBook($year) as $line) {
            self::put($file, self::row($line));
        }
        rewind($file);

        return new Response((string) stream_get_contents($file), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'revenue-book-'.$year.'.csv'),
        ]);
    }

    /**
     * @param resource     $file
     * @param list<string> $row
     */
    private static function put($file, array $row): void
    {
        fputcsv($file, $row, ';', '"', '', "\r\n");
    }

    /** @return list<string> */
    private static function row(RevenueLine $line): array
    {
        return [
            $line->date->format('Y-m-d'),
            $line->invoiceNumber,
            $line->reference,
            1 === preg_match('/^[=+\-@\t\r]/', $line->customerName) ? "'".$line->customerName : $line->customerName,
            $line->country,
            $line->productType,
            number_format($line->amountCents / 100, 2, ',', ''),
            $line->paymentMethod,
        ];
    }
}
