<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\DatabaseFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient(['debug' => false]);
        DatabaseFixture::reset(static::$kernel);
    }

    private function request(string $method, string $uri, array $body = [], ?string $token = 'writer-one'): void
    {
        $this->client->request($method, $uri, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => null === $token ? '' : 'Bearer '.$token], json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function testServerPricingAndAccountIsolation(): void
    {
        $this->request('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 2]);
        self::assertResponseStatusCodeSame(201);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(2500, $body['data']['total_cents']);
        self::assertSame('SGD', $body['data']['currency']);
        self::assertArrayNotHasKey('account_id', $body['data']);
        $uri = '/api/orders/'.$body['data']['id'];
        $this->request('GET', $uri, token: 'writer-two');
        self::assertResponseStatusCodeSame(403);
        $this->request('GET', $uri, token: 'reader-one');
        self::assertResponseStatusCodeSame(200);
    }

    #[DataProvider('invalidBodies')]
    public function testRejectedInputDoesNotCreateAnOrder(array $body, int $status): void
    {
        $this->request('POST', '/api/orders', $body);
        self::assertResponseStatusCodeSame($status);
        self::assertSame(0, (int) static::getContainer()->get('doctrine')->getConnection()->fetchOne('SELECT COUNT(*) FROM orders'));
    }

    public static function invalidBodies(): iterable
    {
        yield 'quantity range' => [['sku' => 'COFFEE', 'quantity' => 0], 422];
        yield 'quantity type' => [['sku' => 'COFFEE', 'quantity' => 'two'], 422];
        yield 'numeric string' => [['sku' => 'COFFEE', 'quantity' => '2'], 422];
        yield 'missing quantity' => [['sku' => 'COFFEE'], 422];
        yield 'price injection' => [['sku' => 'COFFEE', 'quantity' => 2, 'unit_price_cents' => 1], 400];
        yield 'unknown product' => [['sku' => 'MISSING', 'quantity' => 2], 422];
    }

    #[DataProvider('invalidTokens')]
    public function testCredentialsAreRequired(?string $token): void
    {
        $this->request('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 2], $token);
        self::assertResponseStatusCodeSame(401);
    }

    public static function invalidTokens(): iterable
    {
        yield [null];
        yield ['unknown'];
        yield ['expired-one'];
        yield ['revoked-one'];
    }

    public function testReaderCannotCreateOrders(): void
    {
        $this->request('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 2], 'reader-one');
        self::assertResponseStatusCodeSame(403);
    }
}
