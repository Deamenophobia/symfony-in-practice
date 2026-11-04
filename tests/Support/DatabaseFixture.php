<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class DatabaseFixture
{
    public static function reset(Kernel $kernel): Connection
    {
        $kernel->boot();
        $registry = $kernel->getContainer()->get('doctrine');
        $connection = $registry->getConnection();
        if ('test' !== $kernel->getEnvironment() || 'symfony_practice_test' !== $connection->fetchOne('SELECT DATABASE()')) {
            throw new \RuntimeException('Reset is restricted to test / symfony_practice_test.');
        }
        $registry->getManager()->clear();
        foreach (['delivery_attempts', 'fulfillment_tasks', 'api_tokens', 'orders', 'products', 'messenger_messages', 'doctrine_migration_versions'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }
        $application = new Application($kernel);
        $application->setAutoExit(false);
        foreach (['doctrine:migrations:migrate', 'messenger:setup-transports'] as $command) {
            $input = new ArrayInput(['command' => $command, '--no-interaction' => true]);
            $input->setInteractive(false);
            $output = new BufferedOutput();
            if (0 !== $application->run($input, $output)) {
                throw new \RuntimeException($output->fetch());
            }
        }
        foreach ([1 => 1250, 2 => 1900] as $account => $price) {
            $connection->insert('products', ['account_id' => $account, 'sku' => 'COFFEE', 'price_cents' => $price]);
        }
        foreach ([
            ['writer-one', 1, ['orders:read', 'orders:write'], '2099-01-01 00:00:00', null],
            ['reader-one', 1, ['orders:read'], '2099-01-01 00:00:00', null],
            ['writer-two', 2, ['orders:read', 'orders:write'], '2099-01-01 00:00:00', null],
            ['expired-one', 1, ['orders:read'], '2000-01-01 00:00:00', null],
            ['revoked-one', 1, ['orders:read'], '2099-01-01 00:00:00', '2026-01-01 00:00:00'],
        ] as [$token, $account, $abilities, $expires, $revoked]) {
            $connection->insert('api_tokens', ['user_id' => $account, 'account_id' => $account,
                'token_hash' => hash('sha256', $token), 'abilities' => json_encode($abilities),
                'expires_at' => $expires, 'revoked_at' => $revoked]);
        }

        return $connection;
    }
}
