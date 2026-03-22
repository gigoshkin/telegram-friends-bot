<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Repository\BotRepository;
use App\Service\BotInfoService;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class BotDeleteConfirmHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramUserProvider $userProvider,
        private BotRepository $botRepository,
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

        $this->em->remove($entity);
        $this->em->flush();

        // Show updated bots list
        $user = $this->userProvider->getOrCreate($bot->userId());
        $bots = $this->botRepository->findByOwner($user);

        if (empty($bots)) {
            $bot->editMessageText(
                "✅ Bot deleted.\n\nYou don't have any bots left.",
                reply_markup: InlineKeyboardMarkup::make()->addRow(
                    InlineKeyboardButton::make('🤖 Add Bot', callback_data: 'add_bot'),
                ),
            );
            return;
        }

        $keyboard = InlineKeyboardMarkup::make();
        foreach ($bots as $remaining) {
            $label = $this->botInfoService->getDisplayName($remaining) . ' · #' . $remaining->getId();
            $keyboard->addRow(
                InlineKeyboardButton::make($label, callback_data: 'bot_menu:' . $remaining->getId()),
            );
        }
        $keyboard->addRow(InlineKeyboardButton::make('➕ Add new bot', callback_data: 'add_bot'));

        $bot->editMessageText('✅ Bot deleted. Your bots:', reply_markup: $keyboard);
    }
}
