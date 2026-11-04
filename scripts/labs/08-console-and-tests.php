<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

$db = labReset();
labOrder();
$application = new Application(labKernel());
$tester = new CommandTester($application->find('app:orders:reconcile'));
$results = [];
$results['invalid_limit_exit'] = $tester->execute(['--limit' => '0']);
$results['dry_run_exit'] = $tester->execute(['--dry-run' => true]);
$results['dry_run'] = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
$results['queued_after_dry_run'] = (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages');
$results['dispatch_exit'] = $tester->execute(['--limit' => '1']);
$results['dispatch'] = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
$worker = labProcess(['bin/console', 'messenger:consume', 'async', '--limit=1', '--time-limit=5', '--sleep=0.01', '--no-interaction']);
$worker->mustRun();
$tester->execute(['--dry-run' => true]);
$results['after_worker'] = json_decode(trim($tester->getDisplay()), true, flags: JSON_THROW_ON_ERROR);
$results['tasks'] = (int) $db->fetchOne('SELECT COUNT(*) FROM fulfillment_tasks');
labAssert(2 === $results['invalid_limit_exit'] && 0 === $results['dry_run_exit'] && 0 === $results['queued_after_dry_run']
    && $results['dry_run'] === ['order_ids' => [1], 'enqueued' => 0]
    && 0 === $results['dispatch_exit'] && $results['dispatch'] === ['order_ids' => [1], 'enqueued' => 1]
    && $results['after_worker'] === ['order_ids' => [], 'enqueued' => 0] && 1 === $results['tasks'], 'Command contract changed.');
labEvidence('08-console-and-tests', $results);
