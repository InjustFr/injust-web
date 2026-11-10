<?php

declare(strict_types=1);

namespace App\Presentation\Api\Withdrawal;

use App\Application\Sales\PreviewWithdrawal\PreviewWithdrawal;
use App\Application\Sales\PreviewWithdrawal\PreviewWithdrawalHandler;
use App\Presentation\Api\IpRateLimit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/withdrawal/preview', name: 'api_withdrawal_preview', methods: ['POST'], format: 'json')]
final class PreviewWithdrawalController extends AbstractController
{
    public function __invoke(Request $request, #[MapRequestPayload] WithdrawalLookupPayload $payload, PreviewWithdrawalHandler $previewWithdrawal, IpRateLimit $limit, RateLimiterFactoryInterface $withdrawalLimiter): Response
    {
        $refused = $limit->refuse($withdrawalLimiter, $request, 'sales.too_many_withdrawals');
        if (null !== $refused) {
            return $refused;
        }
        $preview = $previewWithdrawal(new PreviewWithdrawal($payload->reference, $payload->email));

        return new JsonResponse([
            'reference' => $preview->reference,
            'productType' => $preview->productType,
            'productLabel' => $preview->productLabel,
            'amountCents' => $preview->amountCents,
            'paidAt' => $preview->paidAt->format(\DATE_ATOM),
            'lastDay' => $preview->lastDay->format('Y-m-d'),
        ]);
    }
}
