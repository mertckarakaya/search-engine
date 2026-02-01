<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $keyword = $request->query->get('q', '');
        $type = $request->query->get('type', '');
        $sort = $request->query->get('sort', '');
        $page = (int) $request->query->get('page', 1);
        $limit = 20; // Show more results on dashboard

        try {
            $result = $this->searchService->search(
                $keyword ?: null,
                $type ?: null,
                $page,
                $limit
            );
        } catch (\Exception $e) {
            $result = [
                'data' => [],
                'meta' => ['page' => 1, 'limit' => $limit, 'total' => 0, 'total_pages' => 0],
                'error' => $e->getMessage(),
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'results' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? [],
            'filters' => [
                'keyword' => $keyword,
                'type' => $type,
                'sort' => $sort,
            ],
            'error' => $result['error'] ?? null,
        ]);
    }
}
