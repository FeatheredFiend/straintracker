<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Deliberate HTTP errors under /api (unknown id, bad JSON, wrong method)
 * come back in the same {"error": "..."} shape as every other API reply,
 * instead of Symfony's HTML error page. Genuine 500s are left to Symfony
 * so the debug page still shows in dev. Runs after the security firewall's
 * own exception listener, which answers 401/403 itself.
 */
#[AsEventListener(event: ExceptionEvent::class, priority: -10)]
class ApiExceptionSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface) {
            return;
        }

        // Entity value resolvers say "App\Entity\Strain object not found by..." - not for users.
        $message = $exception instanceof NotFoundHttpException ? 'Not found.' : $exception->getMessage();

        $event->setResponse(new JsonResponse(['error' => $message], $exception->getStatusCode(), $exception->getHeaders()));
    }
}
