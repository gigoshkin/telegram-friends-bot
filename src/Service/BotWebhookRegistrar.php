<?php

namespace App\Service;

use App\Entity\Bot;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class BotWebhookRegistrar
{
    public function __construct(
        private TokenEncryptionService $encryption,
        private UrlGeneratorInterface  $urlGenerator,
        private LoggerInterface        $logger,
    ) {
    }

    public function register(Bot $bot): void
    {
        $token = $bot->getToken($this->encryption);
        $url = $this->urlGenerator->generate(
            'app_bot_webhook',
            ['telegramUserId' => $bot->getTelegramUserId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Telegram requires HTTPS for webhooks
        $url = preg_replace('/^http:/', 'https:', $url);

        $nutgram = new Nutgram($token);
        $nutgram->setWebhook($url);

        $this->logger->info('Webhook registered for bot', [
            'bot_id'    => $bot->getId(),
            'webhook'   => $url,
        ]);
    }
}
