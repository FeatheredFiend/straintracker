<?php

namespace App\Controller\Api\Admin;

use App\Api\JsonApi;
use App\Api\Presenter;
use App\Entity\Brand;
use App\Entity\Rating;
use App\Entity\StrainType;
use App\Entity\Terpene;
use App\Repository\StrainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * CRUD for the four lookup tables strains draw from. They share one
 * controller because they differ only in their handful of fields.
 */
#[Route('/api/admin/{lookup<brands|types|terpenes|ratings>}')]
class LookupAdminController extends AbstractController
{
    use JsonApi;

    private const ENTITIES = [
        'brands' => Brand::class,
        'types' => StrainType::class,
        'terpenes' => Terpene::class,
        'ratings' => Rating::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StrainRepository $strains,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_admin_lookup_list', methods: ['GET'])]
    public function list(string $lookup): JsonResponse
    {
        $uses = $this->strains->usageCounts($lookup);
        $rows = $this->entityManager->getRepository(self::ENTITIES[$lookup])->findAllOrdered();

        return new JsonResponse(array_map(
            fn (object $row): array => $this->present($lookup, $row) + ['uses' => $uses[$row->getId()] ?? 0],
            $rows,
        ));
    }

    #[Route('', name: 'api_admin_lookup_create', methods: ['POST'])]
    public function create(string $lookup, Request $request): JsonResponse
    {
        $class = self::ENTITIES[$lookup];

        return $this->save($lookup, new $class(), $request, Response::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_lookup_update', methods: ['PUT'])]
    public function update(string $lookup, int $id, Request $request): JsonResponse
    {
        return $this->save($lookup, $this->load($lookup, $id), $request, Response::HTTP_OK);
    }

    #[Route('/{id<\d+>}', name: 'api_admin_lookup_delete', methods: ['DELETE'])]
    public function delete(string $lookup, int $id): Response
    {
        $row = $this->load($lookup, $id);
        $uses = $this->strains->usageCounts($lookup)[$id] ?? 0;
        if ($uses > 0) {
            return new JsonResponse(
                ['error' => sprintf('Still used by %d strain%s - change %s first.', $uses, 1 === $uses ? '' : 's', 1 === $uses ? 'it' : 'them')],
                Response::HTTP_CONFLICT,
            );
        }

        $this->entityManager->remove($row);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function save(string $lookup, object $row, Request $request, int $status): JsonResponse
    {
        $data = $this->payload($request);

        match (true) {
            $row instanceof Brand => $row->setName((string) ($data['name'] ?? '')),
            $row instanceof StrainType => $row
                ->setName((string) ($data['name'] ?? ''))
                ->setPosition((int) ($data['position'] ?? 0)),
            $row instanceof Terpene => $row
                ->setName((string) ($data['name'] ?? ''))
                ->setAroma(isset($data['aroma']) ? (string) $data['aroma'] : null)
                ->setColour((string) ($data['colour'] ?? '#10b981')),
            $row instanceof Rating => $row
                ->setLabel((string) ($data['label'] ?? ''))
                ->setScore((int) ($data['score'] ?? 0))
                ->setColour((string) ($data['colour'] ?? '#10b981')),
        };

        $errors = $this->violations($this->validator->validate($row));
        if ([] !== $errors) {
            return $this->invalid($errors);
        }

        $this->entityManager->persist($row);
        $this->entityManager->flush();

        return new JsonResponse($this->present($lookup, $row) + ['uses' => $this->strains->usageCounts($lookup)[$row->getId()] ?? 0], $status);
    }

    private function load(string $lookup, int $id): object
    {
        return $this->entityManager->find(self::ENTITIES[$lookup], $id)
            ?? throw new NotFoundHttpException('Not found.');
    }

    private function present(string $lookup, object $row): array
    {
        return match ($lookup) {
            'brands' => Presenter::brand($row),
            'types' => Presenter::type($row),
            'terpenes' => Presenter::terpene($row),
            'ratings' => Presenter::rating($row),
        };
    }
}
