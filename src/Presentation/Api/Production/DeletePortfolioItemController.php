<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\DeletePortfolioItem\DeletePortfolioItem;
use App\Application\Production\DeletePortfolioItem\DeletePortfolioItemHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/portfolio/{id}', name: 'api_production_portfolio_delete', requirements: ['id' => Requirement::ULID], methods: ['DELETE'], format: 'json')]
final class DeletePortfolioItemController extends AbstractController
{
    public function __invoke(Ulid $id, DeletePortfolioItemHandler $deletePortfolioItem): Response
    {
        $deletePortfolioItem(new DeletePortfolioItem($id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
