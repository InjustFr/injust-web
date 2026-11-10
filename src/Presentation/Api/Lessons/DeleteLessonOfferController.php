<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\DeleteLessonOffer\DeleteLessonOffer;
use App\Application\Lessons\DeleteLessonOffer\DeleteLessonOfferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/lessons/offers/{id}', name: 'api_lessons_offer_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteLessonOfferController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteLessonOfferHandler $deleteLessonOffer): Response
    {
        $deleteLessonOffer(new DeleteLessonOffer($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
