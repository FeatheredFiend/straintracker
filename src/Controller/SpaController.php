<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the Vite-built React app (public/app/index.html) for every
 * non-API URL, so React Router's client-side routes survive a refresh.
 * Same one-origin setup as the warhammer app: the API is same-origin, so
 * there's no CORS for Hostinger's WAF to trip over.
 */
class SpaController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/app/index.html')]
        private readonly string $spaIndexPath,
    ) {
    }

    /**
     * Lowest priority, so every real route wins first. /api/* is excluded
     * so a mistyped endpoint is a JSON 404, not a page of HTML.
     */
    #[Route(
        '/{reactRoute}',
        name: 'app_spa',
        requirements: ['reactRoute' => '(?!api(/|$)).*'],
        defaults: ['reactRoute' => ''],
        priority: -100,
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        if (!is_file($this->spaIndexPath)) {
            throw new \RuntimeException(sprintf('React build not found at "%s" - run "npm run build" in frontend/ first.', $this->spaIndexPath));
        }

        return new Response(file_get_contents($this->spaIndexPath), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
