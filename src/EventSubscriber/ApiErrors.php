<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiErrors
{
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: -128)]
    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        $status = match (true) {
            $error instanceof HttpExceptionInterface => $error->getStatusCode(),
            $error instanceof ExtraAttributesException => 400,
            $error instanceof \DomainException => 422,
            default => 500,
        };
        $body = ['error' => Response::$statusTexts[$status] ?? 'Error'];
        if ($error->getPrevious() instanceof ValidationFailedException) {
            foreach ($error->getPrevious()->getViolations() as $violation) {
                $body['violations'][] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
        }
        $event->setResponse(new JsonResponse($body, $status, $error instanceof HttpExceptionInterface ? $error->getHeaders() : []));
    }
}
