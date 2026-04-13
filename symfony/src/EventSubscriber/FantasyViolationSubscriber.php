<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\FantasyViolation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber observing and mapping FantasyViolation exceptions into user-friendly API responses.
 */
final class FantasyViolationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 20]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        if (!$throwable instanceof FantasyViolation) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'detail' => $throwable->getMessage(),
            'code' => $throwable->getViolationCode(),
        ], $throwable->getHttpStatus()));
        $event->stopPropagation();
    }
}
