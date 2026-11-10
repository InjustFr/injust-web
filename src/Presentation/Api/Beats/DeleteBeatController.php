<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\DeleteBeat\DeleteBeat;
use App\Application\Beats\DeleteBeat\DeleteBeatHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/beats/{id}', name: 'api_beats_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeleteBeatController extends AbstractController
{
    public function __invoke(Ulid $id, DeleteBeatHandler $deleteBeat): Response
    {
        $deleteBeat(new DeleteBeat($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
