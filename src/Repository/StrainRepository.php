<?php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\Strain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Strain>
 */
class StrainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Strain::class);
    }

    /**
     * Every strain with its lookups fetch-joined, so listing them all costs
     * one query rather than one per strain per relation.
     *
     * @return list<Strain>
     */
    public function findAllWithRelations(): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('b', 't', 'ar', 'mr', 'tp', 'bt')
            ->join('s.brand', 'b')
            ->leftJoin('s.type', 't')
            ->leftJoin('s.aRating', 'ar')
            ->leftJoin('s.mRating', 'mr')
            ->leftJoin('s.terpenes', 'tp')
            ->leftJoin('s.batches', 'bt')
            ->orderBy('b.name', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * How many strains use each row of a lookup table, keyed by lookup id.
     * Lookups nobody uses are absent (treat as 0). Drives the admin
     * screens' "used by" column and refusing to delete rows still in use.
     *
     * @param 'brands'|'types'|'terpenes'|'ratings' $lookup
     *
     * @return array<int, int>
     */
    public function usageCounts(string $lookup): array
    {
        $qb = $this->createQueryBuilder('s')->select('COUNT(DISTINCT s.id) AS uses');

        match ($lookup) {
            'brands' => $qb->join('s.brand', 'l'),
            'types' => $qb->join('s.type', 'l'),
            'terpenes' => $qb->join('s.terpenes', 'l'),
            'ratings' => $qb->join(Rating::class, 'l', 'WITH', 's.aRating = l OR s.mRating = l'),
        };

        $rows = $qb->addSelect('l.id AS id')->groupBy('l.id')->getQuery()->getArrayResult();

        return array_column(array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'uses' => (int) $r['uses']], $rows), 'uses', 'id');
    }
}
