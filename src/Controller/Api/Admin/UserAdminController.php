<?php

namespace App\Controller\Api\Admin;

use App\Api\JsonApi;
use App\Api\Presenter;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/users')]
class UserAdminController extends AbstractController
{
    use JsonApi;

    private const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_users_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(Presenter::user(...), $this->users->findBy([], ['displayName' => 'ASC'])));
    }

    #[Route('', name: 'api_admin_users_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $me): JsonResponse
    {
        return $this->save(new User(), $request, $me, Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_update', methods: ['PUT'])]
    public function update(User $user, Request $request, #[CurrentUser] User $me): JsonResponse
    {
        return $this->save($user, $request, $me, Response::HTTP_OK);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_users_delete', methods: ['DELETE'])]
    public function delete(User $user, #[CurrentUser] User $me): Response
    {
        if ($user->getId() === $me->getId()) {
            return new JsonResponse(['error' => "You can't delete your own account."], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function save(User $user, Request $request, User $me, int $status): JsonResponse
    {
        $data = $this->payload($request);
        $errors = [];
        $isNew = null === $user->getId();

        $user->setEmail((string) ($data['email'] ?? ''));
        $user->setDisplayName((string) ($data['displayName'] ?? ''));

        $makeAdmin = (bool) ($data['isAdmin'] ?? false);
        if (!$makeAdmin && $user->getId() === $me->getId()) {
            $errors['isAdmin'] = "You can't remove your own admin access.";
        } else {
            $user->setAdmin($makeAdmin);
        }

        // Blank password on edit means "keep the current one".
        $password = (string) ($data['password'] ?? '');
        if ('' !== $password || $isNew) {
            if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $errors['password'] = sprintf('Use at least %d characters.', self::MIN_PASSWORD_LENGTH);
            } else {
                $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            }
        }

        $errors += $this->violations($this->validator->validate($user));
        if ([] !== $errors) {
            if (!$isNew) {
                $this->entityManager->refresh($user);
            }

            return $this->invalid($errors);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(Presenter::user($user), $status);
    }
}
