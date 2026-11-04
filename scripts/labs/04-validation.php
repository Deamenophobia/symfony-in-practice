<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$db = labReset();
$valid = ['sku' => 'COFFEE', 'quantity' => 2, 'reference' => 'PO-API'];
$responses = [
    'valid' => labRequest('POST', '/api/orders', $valid),
    'zero_quantity' => labRequest('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 0]),
    'wrong_type' => labRequest('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 'two']),
    'numeric_string' => labRequest('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => '2']),
    'missing_quantity' => labRequest('POST', '/api/orders', ['sku' => 'COFFEE']),
    'malformed_json' => labRequest('POST', '/api/orders', '{'),
    'wrong_media_type' => labRequest('POST', '/api/orders', 'sku=COFFEE', contentType: 'text/plain'),
    'extra_price' => labRequest('POST', '/api/orders', [...$valid, 'unit_price_cents' => 1]),
    'unknown_product' => labRequest('POST', '/api/orders', ['sku' => 'MISSING', 'quantity' => 2]),
];
$results = ['statuses' => array_map(fn (array $response) => $response['status'], $responses),
    'created_order' => $responses['valid']['body'], 'zero_violations' => $responses['zero_quantity']['body'],
    'persisted_orders' => (int) $db->fetchOne('SELECT COUNT(*) FROM orders')];
labAssert($results['statuses'] === ['valid' => 201, 'zero_quantity' => 422, 'wrong_type' => 422, 'numeric_string' => 422, 'missing_quantity' => 422,
    'malformed_json' => 400, 'wrong_media_type' => 415, 'extra_price' => 400, 'unknown_product' => 422], json_encode($results));
labAssert(1 === $results['persisted_orders'] && 2500 === $results['created_order']['data']['total_cents'], 'Invalid requests changed persisted orders.');
labEvidence('04-validation', $results);
