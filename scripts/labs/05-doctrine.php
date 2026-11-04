<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use App\Domain\Money;
use App\Entity\Order;
use App\Infrastructure\SqlRecorder;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;

$db = labReset();
$observer = DriverManager::getConnection($db->getParams());
$entities = labService(EntityManagerInterface::class);
$recorder = labService(SqlRecorder::class);
$order = new Order(1, 'COFFEE', 2, new Money(1250), 'BEFORE');
$entities->persist($order);
$results = ['after_persist_rows' => (int) $observer->fetchOne('SELECT COUNT(*) FROM orders'), 'id_before_flush' => $order->id()];
$recorder->reset();
$entities->flush();
$results['insert_sql'] = $recorder->queries;
$results['after_flush_rows'] = (int) $observer->fetchOne('SELECT COUNT(*) FROM orders');
$recorder->reset();
$results['same_identity'] = $entities->find(Order::class, $order->id()) === $order;
$results['identity_lookup_queries'] = count($recorder->queries);
$id = $order->id();
$entities->clear();
$fresh = $entities->find(Order::class, $id);
$results['new_object_after_clear'] = $fresh !== $order;
$results['reload_sql'] = $recorder->queries;
$fresh->changeReference('AFTER');
$results['before_update_flush'] = $observer->fetchOne('SELECT reference FROM orders WHERE id = ?', [$id]);
$recorder->reset();
$entities->flush();
$results['update_sql'] = $recorder->queries;
$results['after_update_flush'] = $observer->fetchOne('SELECT reference FROM orders WHERE id = ?', [$id]);
try {
    $entities->wrapInTransaction(function () use ($entities): void {
        $entities->persist(new Order(1, 'COFFEE', 1, new Money(1250), 'ROLLBACK'));
        $entities->flush();
        throw new RuntimeException('Synthetic failure after flush.');
    });
} catch (RuntimeException $error) {
    labAssert('Synthetic failure after flush.' === $error->getMessage(), 'Unexpected transaction error.');
}
$results['rows_after_rollback'] = (int) $observer->fetchOne('SELECT COUNT(*) FROM orders');
$results['entity_manager_closed'] = !$entities->isOpen();
labAssert(0 === $results['after_persist_rows'] && null === $results['id_before_flush'] && 1 === $results['after_flush_rows']
    && $results['same_identity'] && 0 === $results['identity_lookup_queries'] && $results['new_object_after_clear']
    && 'BEFORE' === $results['before_update_flush'] && 'AFTER' === $results['after_update_flush']
    && 1 === $results['rows_after_rollback'] && $results['entity_manager_closed'], 'Doctrine persistence invariant failed.');
labEvidence('05-doctrine', $results);
