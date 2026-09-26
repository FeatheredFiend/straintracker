<?php

namespace App\Tests\Controller\Api;

use App\Entity\Strain;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StrainApiTest extends WebTestCase
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
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $body ? json_encode($body) : null);

        return json_decode($this->client->getResponse()->getContent() ?: 'null', true) ?? [];
    }

    public function testEverythingNeedsASignIn(): void
    {
        $data = $this->json('GET', '/api/strains');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Please sign in.', $data['error']);

        self::assertSame(['user' => null], $this->json('GET', '/api/me'));
    }

    public function testJsonLoginStartsASession(): void
    {
        $this->createUser();

        $this->json('POST', '/api/login', ['email' => 'martyn@example.com', 'password' => 'wrong']);
        self::assertResponseStatusCodeSame(401);

        $data = $this->json('POST', '/api/login', ['email' => 'martyn@example.com', 'password' => 'correct-horse']);
        self::assertResponseIsSuccessful();
        self::assertSame('Martyn', $data['user']['displayName']);
        self::assertTrue($data['user']['isAdmin']);

        $this->json('GET', '/api/strains');
        self::assertResponseIsSuccessful();
    }

    public function testCreateAndUpdateAStrainWithBothRatingsAndTerpenes(): void
    {
        $this->client->loginUser($this->createUser('anarlia@example.com', admin: false));
        $brand = $this->createBrand('Hillside');

        $created = $this->json('POST', '/api/strains', [
            'name' => 'Test Driver',
            'brandId' => $brand->getId(),
            'typeId' => $this->type('Sativa Hybrid')->getId(),
            'thcPercent' => '25',
            'price' => 65,
            'genetics' => 'Grape & Candy',
            'terpeneIds' => [$this->terpene('Terpinolene')->getId(), $this->terpene('Myrcene')->getId()],
            'aRatingId' => $this->rating('Terrible')->getId(),
            'mRatingId' => $this->rating('Fantastic')->getId(),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Test Driver', $created['name']);
        // json_encode drops a .0 fraction, so whole numbers arrive as ints.
        self::assertEquals(25, $created['thcPercent']);
        self::assertEquals(65, $created['price']);
        self::assertSame('Terrible', $created['aRating']['label']);
        self::assertSame('Fantastic', $created['mRating']['label']);
        self::assertSame(['Myrcene', 'Terpinolene'], array_column($created['terpenes'], 'name'));

        $updated = $this->json('PUT', '/api/strains/'.$created['id'], [
            'name' => 'Test Driver',
            'brandId' => $brand->getId(),
            'terpeneIds' => [$this->terpene('Limonene')->getId()],
            'aRatingId' => $this->rating('Nice')->getId(),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('Nice', $updated['aRating']['label']);
        self::assertNull($updated['mRating']);
        self::assertNull($updated['type']);
        self::assertSame(['Limonene'], array_column($updated['terpenes'], 'name'));

        $list = $this->json('GET', '/api/strains');
        self::assertCount(1, $list);
    }

    public function testValidationErrorsAreReportedPerField(): void
    {
        $this->client->loginUser($this->createUser());
        $brand = $this->createBrand('Harbour');
        $this->json('POST', '/api/strains', ['name' => 'Zest', 'brandId' => $brand->getId()]);

        $data = $this->json('POST', '/api/strains', [
            'name' => '',
            'thcPercent' => 'lots',
            'terpeneIds' => [999999],
            'mRatingId' => 999999,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertEqualsCanonicalizing(['thcPercent', 'mRatingId', 'terpeneIds', 'name', 'brandId'], array_keys($data['fields']));

        $data = $this->json('POST', '/api/strains', ['name' => 'Zest', 'brandId' => $brand->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('This brand already has a strain with that name.', $data['fields']['name']);
    }

    public function testDeleteAndMissingStrainIsJson404(): void
    {
        $this->client->loginUser($this->createUser());
        $strain = (new Strain())->setName('Mountain Kush')->setBrand($this->createBrand('Riverbank'));
        $this->entityManager->persist($strain);
        $this->entityManager->flush();

        $id = $strain->getId(); // Doctrine nulls the id once the entity is removed

        $this->client->request('DELETE', '/api/strains/'.$id);
        self::assertResponseStatusCodeSame(204);

        $data = $this->json('GET', '/api/strains/'.$id);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Not found.', $data['error']);
    }

    public function testLookupsListEverythingTheFormNeeds(): void
    {
        $this->client->loginUser($this->createUser());
        $this->createBrand('Daybreak');

        $data = $this->json('GET', '/api/lookups');

        self::assertSame(['Daybreak'], array_column($data['brands'], 'name'));
        self::assertSame(['Fantastic', 'Nice', 'Mids', 'Terrible'], array_column($data['ratings'], 'label'));
        self::assertSame('Indica', $data['types'][0]['name']);
        self::assertContains('Caryophyllene', array_column($data['terpenes'], 'name'));
    }
}
