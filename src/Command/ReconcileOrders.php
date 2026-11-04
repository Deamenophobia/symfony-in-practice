<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\FulfillOrder;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:orders:reconcile', description: 'Enqueue orders missing their local fulfillment effect.')]
final class ReconcileOrders extends Command
{
    public function __construct(private readonly Connection $connection, private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum orders to inspect, 1–100.', '20');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report without enqueueing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (false === $limit) {
            $output->writeln('Limit must be an integer from 1 to 100.');

            return Command::INVALID;
        }
        $ids = array_map('intval', $this->connection->fetchFirstColumn('SELECT o.id FROM orders o LEFT JOIN fulfillment_tasks f ON f.order_id = o.id WHERE f.id IS NULL ORDER BY o.id LIMIT '.$limit));
        if (!$input->getOption('dry-run')) {
            foreach ($ids as $id) {
                $this->bus->dispatch(new FulfillOrder($id));
            }
        }
        $output->writeln(json_encode(['order_ids' => $ids, 'enqueued' => $input->getOption('dry-run') ? 0 : count($ids)], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
