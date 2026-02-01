<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api;

use App\Application\Service\SearchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService
    ) {
    }

    #[Route('/search', name: 'api_search', methods: ['GET'])]
    #[RateLimit(limiter: 'api_limit')]
    public function search(Request $request): JsonResponse
    {
        $keyword = $request->query->get('q');
        $type = $request->query->get('type');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 10);

        // Validate type if provided
        if ($type && !in_array($type, ['video', 'article'], true)) {
            return $this->json([
                'error' => 'Invalid type. Must be "video" or "article".'
            ], 400);
        }

        if ($page < 1) {
            $page = 1;
        }

        if ($limit < 1 || $limit > 100) {
            $limit = 10;
        }

        try {
            $result = $this->searchService->search($keyword, $type, $page, $limit);
            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Search failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
