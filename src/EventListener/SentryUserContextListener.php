<?php

namespace App\EventListener;

use Sentry\State\HubInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use function Sentry\configureScope;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
readonly class SentryUserContextListener
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $body = $event->getRequest()->getContent();
        if (empty($body)) {
            return;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return;
        }

        $from = $data['message']['from']
            ?? $data['callback_query']['from']
            ?? $data['edited_message']['from']
            ?? null;

        if (!isset($from['id'])) {
            return;
        }

        $this->hub->configureScope(function (\Sentry\State\Scope $scope) use ($from): void {
            $scope->setUser([
                'id'       => (string) $from['id'],
                'username' => $from['username'] ?? null,
                'name'     => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: null,
            ]);
        });
    }
}