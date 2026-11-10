<?php

declare(strict_types=1);

namespace App\Presentation\Api\Production;

use App\Application\Production\EditPortfolioItem\EditPortfolioItem;
use App\Application\Production\EditPortfolioItem\EditPortfolioItemHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/production/portfolio/{id}', name: 'api_production_portfolio_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditPortfolioItemController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] PortfolioItemPayload $payload, EditPortfolioItemHandler $editPortfolioItem): JsonResponse
    {
        return $this->json($editPortfolioItem(new EditPortfolioItem($id, $payload->details())));
    }
}
