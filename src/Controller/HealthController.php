<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'health', methods: ['GET'], format: 'json')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->attributes->set('_lab_trace', [...$request->attributes->get('_lab_trace', []), 'handler']);

        return new JsonResponse(['ok' => true]);
    }
}
