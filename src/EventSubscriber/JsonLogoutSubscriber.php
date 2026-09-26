<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logout is called with fetch() from the React app, so answer with an empty
 * 204 instead of the default redirect to "/".
 */
#[AsEventListener(event: LogoutEvent::class)]
class JsonLogoutSubscriber
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new Response(null, Response::HTTP_NO_CONTENT));
    }
}
