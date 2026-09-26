<?php

namespace App\Tests;

use App\Entity\Brand;
use App\Entity\Rating;
use App\Entity\StrainType;
use App\Entity\Terpene;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Empties the user-entered tables between tests. The ratings, types and
 * terpenes seeded by the migration are kept, as they would be in real use.
 */
trait DatabaseTestTrait
{
    private EntityManagerInterface $entityManager;

    private function resetDatabase(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        // strain_terpene rows go with their strain (ON DELETE CASCADE).
        $this->entityManager->createQuery('DELETE FROM App\Entity\Strain')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Brand')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function createUser(string $email = 'martyn@example.com', bool $admin = true): User
    {
        $user = (new User())->setEmail($email)->setDisplayName(ucfirst(strstr($email, '@', true)))->setAdmin($admin);
        $user->setPassword(self::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'correct-horse'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createBrand(string $name): Brand
    {
        $brand = (new Brand())->setName($name);
        $this->entityManager->persist($brand);
        $this->entityManager->flush();

        return $brand;
    }

    private function rating(string $label): Rating
    {
        return $this->entityManager->getRepository(Rating::class)->findOneBy(['label' => $label]);
    }

    private function terpene(string $name): Terpene
    {
        return $this->entityManager->getRepository(Terpene::class)->findOneBy(['name' => $name]);
    }

    private function type(string $name): StrainType
    {
        return $this->entityManager->getRepository(StrainType::class)->findOneBy(['name' => $name]);
    }
}
