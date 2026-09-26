<?php

namespace App\Controller\Api;

use App\Api\BatchList;
use App\Api\JsonApi;
use App\Api\Presenter;
use App\Entity\Batch;
use App\Entity\Strain;
use App\Repository\BrandRepository;
use App\Repository\RatingRepository;
use App\Repository\StrainRepository;
use App\Repository\StrainTypeRepository;
use App\Repository\TerpeneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The strains-tried log: any signed-in user can view, add and update.
 */
#[Route('/api')]
class StrainController extends AbstractController
{
    use JsonApi;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StrainRepository $strains,
        private readonly BrandRepository $brands,
        private readonly StrainTypeRepository $types,
        private readonly TerpeneRepository $terpenes,
        private readonly RatingRepository $ratings,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /** Everything the strain screens need for their dropdowns, in one call. */
    #[Route('/lookups', name: 'api_lookups', methods: ['GET'])]
    public function lookups(): JsonResponse
    {
        return new JsonResponse([
            'brands' => array_map(Presenter::brand(...), $this->brands->findAllOrdered()),
            'types' => array_map(Presenter::type(...), $this->types->findAllOrdered()),
            'terpenes' => array_map(Presenter::terpene(...), $this->terpenes->findAllOrdered()),
            'ratings' => array_map(Presenter::rating(...), $this->ratings->findAllOrdered()),
        ]);
    }

    /**
     * The full list, unpaginated - it's a personal log of tens to low
     * hundreds of rows, so the React app searches/filters/sorts in memory.
     */
    #[Route('/strains', name: 'api_strains_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(array_map(Presenter::strain(...), $this->strains->findAllWithRelations()));
    }

    #[Route('/strains/{id<\d+>}', name: 'api_strains_show', methods: ['GET'])]
    public function show(Strain $strain): JsonResponse
    {
        return new JsonResponse(Presenter::strain($strain));
    }

    #[Route('/strains', name: 'api_strains_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->save(new Strain(), $request, Response::HTTP_CREATED);
    }

    #[Route('/strains/{id<\d+>}', name: 'api_strains_update', methods: ['PUT'])]
    public function update(Strain $strain, Request $request): JsonResponse
    {
        return $this->save($strain, $request, Response::HTTP_OK);
    }

    #[Route('/strains/{id<\d+>}', name: 'api_strains_delete', methods: ['DELETE'])]
    public function delete(Strain $strain): Response
    {
        $this->entityManager->remove($strain);
        $this->entityManager->flush();

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Quick "add another batch" from the strain page, without resubmitting
     * the whole strain. Returns the updated strain.
     */
    #[Route('/strains/{id<\d+>}/batches', name: 'api_strains_batch_add', methods: ['POST'])]
    public function addBatch(Strain $strain, Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $rows = array_map(
            static fn (Batch $b): array => ['id' => $b->getId(), 'batchNumber' => $b->getBatchNumber(), 'date' => $b->getDate()->format('Y-m-d')],
            $strain->getBatches()->getValues(),
        );
        $newRow = \count($rows);
        $rows[] = ['batchNumber' => $data['batchNumber'] ?? '', 'date' => $data['date'] ?? null];

        $errors = [];
        foreach (BatchList::apply($strain, $rows) as $path => $message) {
            $errors[str_replace("batches.$newRow.", '', $path)] = $message;
        }
        if ([] !== $errors) {
            return $this->invalid($errors);
        }

        $strain->touch();
        $this->entityManager->flush();

        return new JsonResponse(Presenter::strain($strain), Response::HTTP_CREATED);
    }

    #[Route('/strains/{id<\d+>}/batches/{batchId<\d+>}', name: 'api_strains_batch_delete', methods: ['DELETE'])]
    public function deleteBatch(Strain $strain, int $batchId): JsonResponse
    {
        $batch = $strain->getBatches()->findFirst(static fn (int $i, Batch $b): bool => $b->getId() === $batchId)
            ?? throw $this->createNotFoundException();

        $strain->removeBatch($batch);
        $strain->touch();
        $this->entityManager->flush();

        return new JsonResponse(Presenter::strain($strain));
    }

    private function save(Strain $strain, Request $request, int $status): JsonResponse
    {
        $data = $this->payload($request);
        $errors = [];

        $strain
            ->setName((string) ($data['name'] ?? ''))
            ->setGenetics(isset($data['genetics']) ? (string) $data['genetics'] : null)
            ->setNotes(isset($data['notes']) ? (string) $data['notes'] : null)
            ->setBrand($this->lookup($this->brands, $data, 'brandId', $errors))
            ->setType($this->lookup($this->types, $data, 'typeId', $errors))
            ->setARating($this->lookup($this->ratings, $data, 'aRatingId', $errors))
            ->setMRating($this->lookup($this->ratings, $data, 'mRatingId', $errors))
            ->setThcPercent($this->decimal($data, 'thcPercent', 1, $errors))
            ->setPrice($this->decimal($data, 'price', 2, $errors));

        $terpeneIds = array_values(array_unique(array_map('intval', (array) ($data['terpeneIds'] ?? []))));
        $terpenes = [] === $terpeneIds ? [] : $this->terpenes->findBy(['id' => $terpeneIds]);
        if (\count($terpenes) !== \count($terpeneIds)) {
            $errors['terpeneIds'] = 'One of the chosen terpenes no longer exists.';
        }
        $strain->replaceTerpenes($terpenes);

        // Absent means "leave the batches alone"; an empty list removes them all.
        if (\array_key_exists('batches', $data)) {
            $errors += BatchList::apply($strain, $data['batches']);
        }

        foreach ($this->violations($this->validator->validate($strain)) as $path => $message) {
            $errors['brand' === $path ? 'brandId' : $path] ??= $message;
        }
        if ([] !== $errors) {
            return $this->invalid($errors);
        }

        if (null !== $strain->getId()) {
            // Batch-only edits don't touch the strain row, so bump it here.
            $strain->touch();
        }
        $this->entityManager->persist($strain);
        $this->entityManager->flush();

        return new JsonResponse(Presenter::strain($strain), $status);
    }

    /**
     * Resolves an optional "...Id" field to its lookup row, flagging an id
     * that doesn't exist (e.g. deleted by an admin meanwhile).
     *
     * @template T of object
     *
     * @param \Doctrine\Persistence\ObjectRepository<T> $repository
     * @param array<string, mixed>                       $data
     * @param array<string, string>                      $errors
     *
     * @return T|null
     */
    private function lookup(object $repository, array $data, string $field, array &$errors): ?object
    {
        $id = $data[$field] ?? null;
        if (null === $id || '' === $id) {
            return null;
        }

        $entity = is_numeric($id) ? $repository->find((int) $id) : null;
        if (null === $entity) {
            $errors[$field] = 'That option no longer exists - pick another.';
        }

        return $entity;
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $errors
     */
    private function decimal(array $data, string $field, int $scale, array &$errors): ?string
    {
        $value = $this->decimalOrNull($data[$field] ?? null, $scale);
        if (false === $value) {
            $errors[$field] = 'Enter a number.';

            return null;
        }

        return $value;
    }
}
