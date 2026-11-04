<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Symfony\Component\DependencyInjection\Attribute\When;

#[AsMiddleware(connections: ['default'])]
#[When(env: 'test')]
final readonly class SqlTraceMiddleware implements Middleware
{
    public function __construct(private SqlRecorder $recorder)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return (new LoggingMiddleware($this->recorder))->wrap($driver);
    }
}
