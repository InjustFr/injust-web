<?php

declare(strict_types=1);

namespace App\Presentation\Api\Withdrawal;

use App\Application\Sales\RequestWithdrawal\RequestWithdrawal;
use App\Application\Sales\RequestWithdrawal\RequestWithdrawalHandler;
use App\Presentation\Api\IpRateLimit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/withdrawal', name: 'api_withdrawal_request', methods: ['POST'], format: 'json')]
final class RequestWithdrawalController extends AbstractController
{
    public function __invoke(Request $request, #[MapRequestPayload] WithdrawalPayload $payload, RequestWithdrawalHandler $requestWithdrawal, IpRateLimit $limit, RateLimiterFactoryInterface $withdrawalLimiter): Response
    {
        $refused = $limit->refuse($withdrawalLimiter, $request, 'sales.too_many_withdrawals');
        if (null !== $refused) {
            return $refused;
        }
        $withdrawal = $requestWithdrawal(new RequestWithdrawal($payload->reference, $payload->email, $payload->name, $payload->reason, $payload->locale));

        return new JsonResponse([
            'reference' => $withdrawal->orderReference,
            'receivedAt' => $withdrawal->receivedAt->format(\DATE_ATOM),
            'acknowledged' => null !== $withdrawal->acknowledgedAt,
        ], Response::HTTP_CREATED);
    }
}
