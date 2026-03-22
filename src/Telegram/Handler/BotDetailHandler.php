<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Service\BotInfoService;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class BotDetailHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private BotInfoService $botInfoService,
    ) {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        $data  = $bot->callbackQuery()?->data ?? '';
        $botId = (int) explode(':', $data, 2)[1];

        /** @var Bot|null $entity */
        $entity = $this->em->getRepository(Bot::class)->find($botId);

        if (!$entity || $entity->getOwner()->getTelegramId() !== (string)$bot->userId()) {
            $bot->editMessageText('Bot not found.');
            return;
        }

        $bot->editMessageText(
            $this->buildText($entity),
            reply_markup: $this->buildKeyboard($entity),
            parse_mode: 'HTML',
        );
    }

    public function buildText(Bot $entity): string
    {
        $name    = $this->botInfoService->getDisplayName($entity);
        $probPct = (int) round($entity->getResponseProbability() * 100);
        $simPct  = (int) round($entity->getMinSimilarity() * 100);

        $status = $entity->isTrained() ? '✅ Active' : '⏳ Setting up...';
        $debug  = $entity->isDebugMode() ? '🟢 ON' : '⚫ OFF';

        return implode("\n", [
            "<b>🤖 {$name}</b>",
            "Telegram ID: <code>{$entity->getTelegramUserId()}</code> · DB #{$entity->getId()}",
            "Status: {$status}",
            '',
            '⚙️ <b>Configuration</b>',
            "• Response probability: <b>{$probPct}%</b>",
            "• Min similarity (trigram): <b>{$simPct}%</b>",
            "• Debug mode: <b>{$debug}</b>",
        ]);
    }

    public function buildKeyboard(Bot $entity): InlineKeyboardMarkup
    {
        $id      = $entity->getId();
        $probPct = (int) round($entity->getResponseProbability() * 100);
        $simPct  = (int) round($entity->getMinSimilarity() * 100);

        $debugLabel = '🐛 Debug mode: ' . ($entity->isDebugMode() ? 'ON' : 'OFF');

        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make(
                    "📊 Response probability: {$probPct}%",
                    callback_data: "bot_config:{$id}:responseProbability",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make(
                    "🎯 Min similarity: {$simPct}%",
                    callback_data: "bot_config:{$id}:minSimilarity",
                ),
            )
            ->addRow(
                InlineKeyboardButton::make($debugLabel, callback_data: "bot_debug_toggle:{$id}"),
            )
            ->addRow(
                InlineKeyboardButton::make('🗑 Delete bot', callback_data: "bot_delete:{$id}"),
                InlineKeyboardButton::make('← Bots', callback_data: 'bots_menu'),
            );
    }
}
