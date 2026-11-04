<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

final class RequestTrace
{
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 1024)]
    public function request(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $event->getRequest()->attributes->set('_request_id', (string) Uuid::v4());
        $event->getRequest()->attributes->set('_lab_trace', ['request']);
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER)]
    public function controller(ControllerEvent $event): void
    {
        $this->append($event, 'controller');
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER_ARGUMENTS, priority: -128)]
    public function arguments(ControllerArgumentsEvent $event): void
    {
        $this->append($event, 'arguments');
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 1024)]
    public function exception(ExceptionEvent $event): void
    {
        $this->append($event, 'exception');
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1024)]
    public function response(ResponseEvent $event): void
    {
        $this->append($event, 'response');
        if (!$event->isMainRequest()) {
            return;
        }
        $event->getResponse()->headers->set('X-Request-ID', $event->getRequest()->attributes->get('_request_id', ''));
        $event->getResponse()->headers->set('X-Lab-Trace', implode(',', $event->getRequest()->attributes->get('_lab_trace', [])));
    }

    private function append(object $event, string $stage): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $request->attributes->set('_lab_trace', [...$request->attributes->get('_lab_trace', []), $stage]);
    }
}
