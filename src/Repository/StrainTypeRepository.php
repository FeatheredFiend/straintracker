<?php

namespace App\Repository;

use App\Entity\StrainType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StrainType>
 */
class StrainTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StrainType::class);
    }

    /** @return list<StrainType> */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
    }
}
