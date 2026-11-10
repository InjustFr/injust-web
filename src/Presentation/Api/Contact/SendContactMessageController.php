<?php

declare(strict_types=1);

namespace App\Presentation\Api\Contact;

use App\Application\Contact\SendContactMessage\SendContactMessage;
use App\Application\Contact\SendContactMessage\SendContactMessageHandler;
use App\Presentation\Api\ProblemResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/contact', name: 'api_contact_send', methods: ['POST'], format: 'json')]
final class SendContactMessageController extends AbstractController
{
    public function __invoke(Request $request, #[MapRequestPayload] ContactPayload $payload, SendContactMessageHandler $sendContactMessage, RateLimiterFactoryInterface $contactLimiter, TranslatorInterface $translator): Response
    {
        if (!$contactLimiter->create(IpUtils::anonymize($request->getClientIp() ?? '0.0.0.0', 0, 8))->consume()->isAccepted()) {
            return new ProblemResponse(Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS], Response::HTTP_TOO_MANY_REQUESTS, $translator->trans('contact.too_many_messages', [], 'exceptions'));
        }
        if ('' === $payload->website) {
            $sendContactMessage(new SendContactMessage($payload->name, $payload->email, $payload->topic, $payload->body, $request->getLocale(), $payload->offerRef));
        }

        return new JsonResponse(new \stdClass(), Response::HTTP_CREATED);
    }
}
