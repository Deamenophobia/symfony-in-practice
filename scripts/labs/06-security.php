<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

$db = labReset();
$order = labOrder();
$uri = '/api/orders/'.$order->id();
$results = [
    'missing_token' => labRequest('GET', $uri, token: null)['status'],
    'invalid_token' => labRequest('GET', $uri, token: 'unknown')['status'],
    'same_account' => labRequest('GET', $uri)['status'],
    'other_account' => labRequest('GET', $uri, token: 'writer-two')['status'],
    'read_token_read' => labRequest('GET', $uri, token: 'reader-one')['status'],
    'read_token_create' => labRequest('POST', '/api/orders', ['sku' => 'COFFEE', 'quantity' => 1], 'reader-one')['status'],
    'expired' => labRequest('GET', $uri, token: 'expired-one')['status'],
    'already_revoked' => labRequest('GET', $uri, token: 'revoked-one')['status'],
];
$db->executeStatement('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ?', [hash('sha256', 'writer-one')]);
$results['after_revocation'] = labRequest('GET', $uri)['status'];
labAssert(array_values($results) === [401, 401, 200, 403, 200, 403, 401, 401, 401], 'Security result changed: '.json_encode($results));
labEvidence('06-security', $results);
