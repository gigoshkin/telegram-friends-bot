<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class ExportsMenuHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramUserProvider   $userProvider,
    )
    {
    }

    public function __invoke(Nutgram $bot): void
    {
        if ($bot->callbackQuery()) {
            $bot->answerCallbackQuery();
        }

        $user = $this->userProvider->getOrCreate($bot->userId());
        $exports = $this->em->getRepository(ChatExportFile::class)->findBy(
            ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        if (empty($exports)) {
            $text = "You have no uploaded chat exports.";
            $keyboard = null;
        } else {
            $text = "Your chat exports:\n\n<i>Tap an export to delete it.</i>";
            $keyboard = InlineKeyboardMarkup::make();

            foreach ($exports as $export) {
                $botCount = $this->em->getRepository(Bot::class)->count(['chatExportFile' => $export]);
                $name = $export->getChatName() ?? 'Unnamed chat';
                $date = $export->getCreatedAt()?->format('d M Y') ?? '';
                $status = $export->isImported() ? '' : ' · ⏳ importing';
                $bots = $botCount > 0 ? " · {$botCount} bot" . ($botCount > 1 ? 's' : '') : '';

                $keyboard->addRow(
                    InlineKeyboardButton::make(
                        "🗑 {$name} ({$date}){$bots}{$status}",
                        callback_data: 'export_delete:' . $export->getId(),
                    )
                );
            }
        }

        if ($bot->callbackQuery()) {
            $bot->editMessageText($text, reply_markup: $keyboard, parse_mode: 'HTML');
        } else {
            $bot->sendMessage($text, reply_markup: $keyboard, parse_mode: 'HTML');
        }
    }
}