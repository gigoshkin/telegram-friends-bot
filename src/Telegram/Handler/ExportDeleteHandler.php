<?php

namespace App\Telegram\Handler;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

readonly class ExportDeleteHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private TelegramUserProvider   $userProvider,
    )
    {
    }

    public function __invoke(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();

        $data = $bot->callbackQuery()?->data ?? '';
        $exportId = (int)explode(':', $data, 2)[1];

        $export = $this->em->find(ChatExportFile::class, $exportId);
        $user = $this->userProvider->getOrCreate($bot->userId());

        if (!$export || $export->getOwner()?->getId() !== $user->getId()) {
            $bot->editMessageText('Export not found.');
            return;
        }

        $botCount = $this->em->getRepository(Bot::class)->count(['chatExportFile' => $export]);
        $name = $export->getChatName() ?? 'Unnamed chat';
        $date = $export->getCreatedAt()?->format('d M Y') ?? '';

        $warning = $botCount > 0
            ? "\n\n⚠️ <b>{$botCount} bot" . ($botCount > 1 ? 's are' : ' is') . " using this export</b> and will be deactivated."
            : '';

        $bot->editMessageText(
            "🗑 Delete <b>{$name}</b> ({$date})?{$warning}\n\nAll imported messages will be permanently removed.",
            parse_mode: 'HTML',
            reply_markup: InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('✅ Yes, delete', callback_data: 'export_delete_confirm:' . $exportId),
                    InlineKeyboardButton::make('✖ Cancel', callback_data: 'exports_menu'),
                ),
        );
    }
}