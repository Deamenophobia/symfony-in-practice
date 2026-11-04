<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Message\FulfillOrder;
use Symfony\Component\Messenger\MessageBusInterface;

$db = labReset();
$order = labOrder();
$bus = labService(MessageBusInterface::class);
foreach (range(1, 3) as $delivery) {
    $bus->dispatch(new FulfillOrder($order->id()));
}
$results = ['before_worker' => [
    'queued' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async'"),
    'tasks' => (int) $db->fetchOne('SELECT COUNT(*) FROM fulfillment_tasks'),
]];
$worker = labProcess(['bin/console', 'messenger:consume', 'async', '--limit=4', '--time-limit=5', '--sleep=0.01', '--no-interaction', '-v'], ['LAB_FAIL_FIRST' => '1']);
$worker->mustRun();
$directory = dirname(__DIR__, 2).'/.runtime';
if (!is_dir($directory)) {
    mkdir($directory);
}
file_put_contents($directory.'/messenger-first-worker.log', $worker->getOutput().$worker->getErrorOutput());
$results['after_retry_and_duplicates'] = [
    'attempts' => (int) $db->fetchOne('SELECT attempts FROM delivery_attempts WHERE order_id = ?', [$order->id()]),
    'tasks' => (int) $db->fetchOne('SELECT COUNT(*) FROM fulfillment_tasks WHERE order_id = ?', [$order->id()]),
    'queued' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async'"),
    'failed' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"),
];
labAssert($results['before_worker'] === ['queued' => 3, 'tasks' => 0], 'Dispatch unexpectedly ran the handler.');
labAssert($results['after_retry_and_duplicates'] === ['attempts' => 4, 'tasks' => 1, 'queued' => 0, 'failed' => 0], 'Repeated delivery invariant failed: '.json_encode($results));
$bus->dispatch(new FulfillOrder(999999));
$invalidWorker = labProcess(['bin/console', 'messenger:consume', 'async', '--limit=3', '--time-limit=5', '--sleep=0.01', '--no-interaction', '-v'], ['LAB_FAIL_FIRST' => '0']);
$invalidWorker->mustRun();
$results['unknown_order'] = [
    'queued' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'async'"),
    'failed' => (int) $db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'"),
    'tasks' => (int) $db->fetchOne('SELECT COUNT(*) FROM fulfillment_tasks'),
];
labAssert($results['unknown_order'] === ['queued' => 0, 'failed' => 1, 'tasks' => 1], 'Failed transport did not retain the rejected message.');
$directory = dirname(__DIR__, 2).'/.runtime';
if (!is_dir($directory)) {
    mkdir($directory);
}
file_put_contents($directory.'/messenger-workers.log', $worker->getOutput().$worker->getErrorOutput().$invalidWorker->getOutput().$invalidWorker->getErrorOutput());
labEvidence('07-messenger', $results);
