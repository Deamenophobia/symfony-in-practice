<?php

declare(strict_types=1);

use App\Application\PlaceOrder;
use App\Dto\CreateOrder;
use App\Entity\Order;
use App\Kernel;
use App\Tests\Support\DatabaseFixture;
use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

function labKernel(): Kernel
{
    static $kernel;
    $kernel ??= new Kernel('test', false);
    $kernel->boot();

    return $kernel;
}

function labService(string $id): mixed
{
    return labKernel()->getContainer()->get('test.service_container')->get($id);
}

function labReset(): Connection
{
    return DatabaseFixture::reset(labKernel());
}
function labDb(): Connection
{
    return labKernel()->getContainer()->get('doctrine')->getConnection();
}

function labOrder(int $account = 1): Order
{
    return labService(PlaceOrder::class)->place($account, new CreateOrder('COFFEE', 2, 'PO-100'));
}

function labRequest(string $method, string $uri, array|string $body = [], ?string $token = 'writer-one', string $contentType = 'application/json'): array
{
    $kernel = new Kernel('test', false);
    $client = new KernelBrowser($kernel);
    try {
        $client->request($method, $uri, [], [], ['CONTENT_TYPE' => $contentType, 'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => null === $token ? '' : 'Bearer '.$token], is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR));
        $response = $client->getResponse();

        return ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true),
            'request_id' => $response->headers->get('X-Request-ID'), 'trace' => $response->headers->get('X-Lab-Trace')];
    } finally {
        $kernel->shutdown();
    }
}

function labAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function labEvidence(string $name, array $results): void
{
    $packages = [];
    foreach (['symfony/framework-bundle', 'symfony/http-kernel', 'symfony/messenger', 'doctrine/orm', 'doctrine/dbal'] as $package) {
        $packages[$package] = InstalledVersions::getPrettyVersion($package);
    }
    $evidence = ['recorded_at' => gmdate('c'), 'php' => PHP_VERSION,
        'mysql' => labDb()->fetchOne('SELECT VERSION()'), 'database' => labDb()->fetchOne('SELECT DATABASE()'),
        'packages' => $packages, 'results' => $results];
    $directory = dirname(__DIR__, 2).('1' === getenv('LAB_VERIFY_ONLY') ? '/.runtime/verification' : '/docs/evidence');
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    file_put_contents($directory.'/'.$name.'.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}

function labProcess(array $arguments, array $environment = []): Process
{
    $process = new Process([PHP_BINARY, ...$arguments], dirname(__DIR__, 2), array_merge([
        'APP_ENV' => 'test', 'APP_DEBUG' => '0', 'DATABASE_URL' => $_ENV['DATABASE_URL'],
    ], $environment));
    $process->setTimeout(15);

    return $process;
}
