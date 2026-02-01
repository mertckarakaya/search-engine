<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ApiRateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiLimitLimiter
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Create limiter using client IP
        $limiter = $this->apiLimitLimiter->create($request->getClientIp());

        // Try to consume 1 token
        $limit = $limiter->consume(1);

        // If rate limit exceeded, return 429
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $response = new JsonResponse([
                'error' => 'Rate limit exceeded. Too many requests.',
                'message' => 'You have exceeded the API rate limit of 100 requests per hour.',
                'retry_after' => $retryAfter->getTimestamp(),
                'retry_after_seconds' => $retryAfter->getTimestamp() - time(),
            ], 429);

            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());
            $response->headers->set('Retry-After', (string) ($retryAfter->getTimestamp() - time()));

            $event->setResponse($response);
            return;
        }

        // Add rate limit headers to successful responses
        $event->getRequest()->attributes->set('rate_limit_remaining', $limit->getRemainingTokens());
        $event->getRequest()->attributes->set('rate_limit_limit', $limit->getLimit());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }
}
