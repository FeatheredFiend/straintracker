<?php

namespace App\Tests\Controller\Api;

use App\Entity\Batch;
use App\Entity\Brand;
use App\Tests\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BatchApiTest extends WebTestCase
{
    use DatabaseTestTrait;

    private KernelBrowser $client;
    private Brand $brand;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->resetDatabase();
        $this->client->loginUser($this->createUser());
        $this->brand = $this->createBrand('Hillside');
    }

    private function json(string $method, string $uri, ?array $body = null): array
    {
        $this->client->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: $body ? json_encode($body) : null);

        return json_decode($this->client->getResponse()->getContent() ?: 'null', true) ?? [];
    }

    /** @param list<array<string, mixed>> $batches */
    private function createStrain(array $batches): array
    {
        return $this->json('POST', '/api/strains', ['name' => 'Test Driver', 'brandId' => $this->brand->getId(), 'batches' => $batches]);
    }

    public function testBatchesAreAddedWithTheStrainAndListedNewestFirst(): void
    {
        $strain = $this->createStrain([
            ['batchNumber' => 'B-1001', 'date' => '2026-03-14'],
            ['batchNumber' => ' B-1002 ', 'date' => '2026-08-01'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['B-1002', 'B-1001'], array_column($strain['batches'], 'batchNumber'));
        self::assertSame(['2026-08-01', '2026-03-14'], array_column($strain['batches'], 'date'));

        $reloaded = $this->json('GET', '/api/strains/'.$strain['id']);
        self::assertSame($strain['batches'], $reloaded['batches']);
    }

    public function testEditingSyncsTheList(): void
    {
        $strain = $this->createStrain([
            ['batchNumber' => 'KEEP', 'date' => '2026-01-01'],
            ['batchNumber' => 'DROP', 'date' => '2026-02-01'],
        ]);
        $keep = array_column($strain['batches'], null, 'batchNumber')['KEEP'];

        $updated = $this->json('PUT', '/api/strains/'.$strain['id'], [
            'name' => 'Test Driver',
            'brandId' => $this->brand->getId(),
            'batches' => [
                ['id' => $keep['id'], 'batchNumber' => 'KEEP-2', 'date' => '2026-01-05'],
                ['batchNumber' => 'NEW', 'date' => '2026-09-26'],
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(['NEW', 'KEEP-2'], array_column($updated['batches'], 'batchNumber'));
        self::assertSame($keep['id'], $updated['batches'][1]['id'], 'existing batch updated in place');
        self::assertSame(2, $this->entityManager->getRepository(Batch::class)->count([]), 'removed batch is deleted');
    }

    public function testLeavingOutBatchesKeepsThem(): void
    {
        $strain = $this->createStrain([['batchNumber' => 'B1', 'date' => '2026-01-01']]);

        $updated = $this->json('PUT', '/api/strains/'.$strain['id'], ['name' => 'Renamed', 'brandId' => $this->brand->getId()]);

        self::assertSame(['B1'], array_column($updated['batches'], 'batchNumber'));
    }

    public function testRowErrorsPointAtTheRow(): void
    {
        $data = $this->createStrain([
            ['batchNumber' => 'A1', 'date' => '2026-01-01'],
            ['batchNumber' => '', 'date' => '2026-02-30'],
            ['batchNumber' => 'a1', 'date' => 'yesterday'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'batches.1.batchNumber' => 'Enter the batch number, or remove this batch.',
            'batches.1.date' => 'Enter a valid date.',
            'batches.2.batchNumber' => 'This batch number is already listed.',
            'batches.2.date' => 'Enter a valid date.',
        ], $data['fields']);
        self::assertSame(0, $this->entityManager->getRepository(Batch::class)->count([]));
    }

    public function testQuickAddAndRemoveFromTheStrainPage(): void
    {
        $strain = $this->createStrain([['batchNumber' => 'B1', 'date' => '2026-01-01']]);

        $updated = $this->json('POST', "/api/strains/{$strain['id']}/batches", ['batchNumber' => 'B2', 'date' => '2026-09-01']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame(['B2', 'B1'], array_column($updated['batches'], 'batchNumber'));

        $data = $this->json('POST', "/api/strains/{$strain['id']}/batches", ['batchNumber' => 'b2', 'date' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['batchNumber' => 'This batch number is already listed.', 'date' => 'Enter a valid date.'], $data['fields']);

        $b1 = $updated['batches'][1]['id'];
        $afterDelete = $this->json('DELETE', "/api/strains/{$strain['id']}/batches/$b1");
        self::assertResponseIsSuccessful();
        self::assertSame(['B2'], array_column($afterDelete['batches'], 'batchNumber'));

        $this->json('DELETE', "/api/strains/{$strain['id']}/batches/$b1");
        self::assertResponseStatusCodeSame(404);
    }

    public function testBatchesGoWithTheirStrain(): void
    {
        $strain = $this->createStrain([['batchNumber' => 'B1', 'date' => '2026-01-01']]);

        $this->client->request('DELETE', '/api/strains/'.$strain['id']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->entityManager->getRepository(Batch::class)->count([]));
    }

    public function testTheSameBatchNumberCanBeUsedOnDifferentStrains(): void
    {
        $this->createStrain([['batchNumber' => 'LOT-7', 'date' => '2026-01-01']]);
        $this->json('POST', '/api/strains', ['name' => 'Other', 'brandId' => $this->brand->getId(), 'batches' => [['batchNumber' => 'LOT-7', 'date' => '2026-01-01']]]);

        self::assertResponseStatusCodeSame(201);
    }
}
