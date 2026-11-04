<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

$request = Request::create('/quote?quantity=2', 'GET');
$response = new JsonResponse(['quantity' => $request->query->get('quantity')]);
$healthy = labRequest('GET', '/health', token: null);
$missing = labRequest('GET', '/missing', token: null);
labAssert(200 === $healthy['status'] && 'request,controller,arguments,handler,response' === $healthy['trace'], 'Successful kernel trace changed.');
labAssert(404 === $missing['status'] && 'request,exception,response' === $missing['trace'], 'Missing-route trace changed.');
labAssert(null !== $healthy['request_id'] && null !== $missing['request_id'], 'Request correlation is missing.');
labEvidence('01-http-kernel', ['component_response' => json_decode($response->getContent(), true), 'query_value_type' => get_debug_type($request->query->get('quantity')),
    'healthy' => ['status' => $healthy['status'], 'trace' => $healthy['trace']], 'missing' => ['status' => $missing['status'], 'trace' => $missing['trace']], 'ids_present' => true]);
