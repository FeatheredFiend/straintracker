<?php

namespace App\Controller\Api;

use App\Api\Presenter;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api')]
class AuthController extends AbstractController
{
    /**
     * json_login authenticates this request before it gets here; bad
     * credentials are answered by the firewall with a JSON 401.
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return new JsonResponse(['error' => 'Send JSON with "email" and "password".'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['user' => Presenter::user($user)]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        return new JsonResponse(['user' => $user ? Presenter::user($user) : null]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall logout listener.');
    }
}
