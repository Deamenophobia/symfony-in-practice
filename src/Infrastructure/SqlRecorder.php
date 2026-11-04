<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Psr\Log\AbstractLogger;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Contracts\Service\ResetInterface;

#[When(env: 'test')]
final class SqlRecorder extends AbstractLogger implements ResetInterface
{
    public array $queries = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (isset($context['sql'])) {
            $this->queries[] = ['sql' => $context['sql'], 'params' => $context['params'] ?? []];
        }
    }

    public function reset(): void
    {
        $this->queries = [];
    }
}
