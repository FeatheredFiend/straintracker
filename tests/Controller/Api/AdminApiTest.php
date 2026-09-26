<?php

namespace App\Tests\Controller\Api;

use App\Entity\Brand;
use App\Entity\Strain;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
    }

    private function json(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: $body ? json_encode($body) : null);

        return json_decode($this->client->getResponse()->getContent() ?: 'null', true) ?? [];
    }

    public function testNonAdminsAreKeptOut(): void
    {
        $this->client->loginUser($this->createUser('anarlia@example.com', admin: false));

        foreach (['/api/admin/brands', '/api/admin/users'] as $uri) {
            $data = $this->json('GET', $uri);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('You do not have access to this.', $data['error']);
        }
    }

    public function testBrandCrudWithUsageCountsAndInUseProtection(): void
    {
        $this->client->loginUser($this->createUser());

        $brand = $this->json('POST', '/api/admin/brands', ['name' => '  Meadow Farms ']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('Meadow Farms', $brand['name']);
        self::assertSame(0, $brand['uses']);

        $this->json('POST', '/api/admin/brands', ['name' => 'Meadow Farms']);
        self::assertResponseStatusCodeSame(422);

        $strain = (new Strain())->setName('Lemon Rocket')->setBrand($this->entityManager->find(Brand::class, $brand['id']));
        $this->entityManager->persist($strain);
        $this->entityManager->flush();

        $list = $this->json('GET', '/api/admin/brands');
        self::assertSame(1, $list[0]['uses']);

        $data = $this->json('DELETE', '/api/admin/brands/'.$brand['id']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Still used by 1 strain - change it first.', $data['error']);

        $renamed = $this->json('PUT', '/api/admin/brands/'.$brand['id'], ['name' => 'Meadow Farms Co']);
        self::assertSame('Meadow Farms Co', $renamed['name']);
    }

    public function testRatingUsageCountsBothPeople(): void
    {
        $this->client->loginUser($this->createUser());
        $brand = $this->createBrand('Greenleaf');
        $mids = $this->rating('Mids');
        $this->entityManager->persist((new Strain())->setName('Velvet Cake')->setBrand($brand)->setARating($mids)->setMRating($mids));
        $this->entityManager->persist((new Strain())->setName('Other')->setBrand($brand)->setMRating($mids));
        $this->entityManager->flush();

        $ratings = array_column($this->json('GET', '/api/admin/ratings'), 'uses', 'label');

        self::assertSame(2, $ratings['Mids']);
        self::assertSame(0, $ratings['Fantastic']);
    }

    public function testTerpeneColourIsValidated(): void
    {
        $this->client->loginUser($this->createUser());
        $this->entityManager->createQuery("DELETE FROM App\Entity\Terpene t WHERE t.name = 'Camphene'")->execute();

        $data = $this->json('POST', '/api/admin/terpenes', ['name' => 'Camphene', 'colour' => 'green']);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('colour', $data['fields']);

        $data = $this->json('POST', '/api/admin/terpenes', ['name' => 'Camphene', 'aroma' => 'Damp woodland', 'colour' => '#2F855A']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('#2f855a', $data['colour']);

        $this->json('DELETE', '/api/admin/terpenes/'.$data['id']);
        self::assertResponseStatusCodeSame(204);
    }

    public function testUserAdmin(): void
    {
        $me = $this->createUser();
        $this->client->loginUser($me);

        $data = $this->json('POST', '/api/admin/users', ['email' => 'anarlia@example.com', 'displayName' => 'Anarlia', 'password' => 'short']);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('password', $data['fields']);

        $anarlia = $this->json('POST', '/api/admin/users', ['email' => 'Anarlia@Example.com', 'displayName' => 'Anarlia', 'password' => 'long-enough-pw']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('anarlia@example.com', $anarlia['email']);
        self::assertFalse($anarlia['isAdmin']);

        $data = $this->json('PUT', '/api/admin/users/'.$me->getId(), ['email' => $me->getEmail(), 'displayName' => 'Martyn', 'isAdmin' => false]);
        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('isAdmin', $data['fields']);

        $this->json('DELETE', '/api/admin/users/'.$me->getId());
        self::assertResponseStatusCodeSame(409);

        $this->json('DELETE', '/api/admin/users/'.$anarlia['id']);
        self::assertResponseStatusCodeSame(204);
    }
}
