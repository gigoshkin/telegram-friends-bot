<?php

namespace App\Controller;

use App\Repository\BotRepository;
use App\Service\BotResponder\BotResponderInterface;
use App\Service\TokenEncryptionService;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Nutgram;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BotWebhookController extends AbstractController
{
    public function __construct(
        private readonly BotRepository          $botRepository,
        private readonly BotResponderInterface  $responder,
        private readonly TokenEncryptionService $encryption,
        private readonly LoggerInterface        $logger,
    )
    {
    }

    #[Route('/bot-hook/{telegramUserId}', name: 'app_bot_webhook', methods: ['POST'])]
    public function handle(string $telegramUserId, Request $request): Response
    {
        $bot = $this->botRepository->findOneBy([
            'telegramUserId' => $telegramUserId,
            'isTrained' => true,
        ]);

        if (!$bot) {
            return new Response('', Response::HTTP_OK);
        }

        $secret = $bot->getWebhookSecret();
        if ($secret !== null && !hash_equals($secret, (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token', ''))) {
            return new Response('', Response::HTTP_OK);
        }

        $data = json_decode($request->getContent(), true);

        $messageText = $data['message']['text'] ?? null;
        $chatId = $data['message']['chat']['id'] ?? null;
        $messageId = $data['message']['message_id'] ?? null;

        if (!$messageText || !$chatId) {
            return new Response('', Response::HTTP_OK);
        }

        try {
            $reply = $this->responder->respond($bot, $messageText);

            if ($reply !== null) {
                $replyToMessageId = null;
                if ($messageId && $bot->getDirectResponseProbability() > 0) {
                    if ((mt_rand() / mt_getrandmax()) <= $bot->getDirectResponseProbability()) {
                        $replyToMessageId = $messageId;
                    }
                }

                $token = $bot->getToken($this->encryption);
                $nutgram = new Nutgram($token);
                $nutgram->sendMessage(
                    $reply,
                    chat_id: $chatId,
                    parse_mode: $bot->isDebugMode() ? 'HTML' : null,
                    reply_to_message_id: $replyToMessageId,
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error('Bot webhook error', [
                'telegram_user_id' => $telegramUserId,
                'error' => $e->getMessage(),
            ]);
        }

        return new Response('', Response::HTTP_OK);
    }
}
