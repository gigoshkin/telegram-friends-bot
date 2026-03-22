<?php

namespace App\Telegram\Conversation;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Entity\User;
use App\Exception\Service\BotFactory\BotAlreadyExistsException;
use App\Exception\Service\BotFactory\InvalidTokenException;
use App\Exception\Service\ChatExportFileHandler\FileNotFoundException;
use App\Exception\Service\ChatExportFileHandler\FileTooBigException;
use App\Exception\Service\ChatExportFileHandler\InvalidMimeTypeException;
use App\Message\ProcessChatExportMessage;
use App\Repository\ChatMessageRepository;
use App\Service\BotFactory;
use App\Service\BotTrainer\BotTrainerInterface;
use App\Service\BotWebhookRegistrar;
use App\Service\ChatExportFileHandler;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Symfony\Component\Messenger\MessageBusInterface;

class AddBotConversation extends Conversation
{
    private ?int $botId = null;

    public function __construct(
        private readonly TelegramUserProvider   $userProvider,
        private readonly BotFactory             $botFactory,
        private readonly MessageBusInterface    $messageBus,
        private readonly EntityManagerInterface $em,
        private readonly ChatExportFileHandler  $fileHandler,
        private readonly ChatMessageRepository  $chatMessageRepository,
        private readonly BotTrainerInterface    $botTrainer,
        private readonly BotWebhookRegistrar    $webhookRegistrar,
        private readonly LoggerInterface        $logger,
    )
    {
    }

    protected function getSerializableAttributes(): array
    {
        return ['botId' => $this->botId];
    }

    public function start(Nutgram $bot): void
    {
        $userId = $bot->userId();
        $user = $this->userProvider->getOrCreate($userId);

        if ($bot->chat()->type !== ChatType::PRIVATE) {
            $bot->sendMessage($this->msgPrivateChatOnly());
            $this->end();
            return;
        }

        if ($this->hasTrainingBot($user)) {
            $bot->sendMessage($this->msgAlreadyTraining());
            $this->end();
            return;
        }

        $bot->sendMessage($this->msgSetupInstructions());
        $this->next('receiveToken');
    }

    public function receiveToken(Nutgram $bot): void
    {
        $token = trim($bot->message()->text ?? '');
        $user = $this->userProvider->getOrCreate($bot->userId());

        try {
            $userBot = $this->botFactory->create($user, $token);
            $this->botId = $userBot->getId();
            $this->em->flush();
        } catch (BotAlreadyExistsException) {
            $bot->sendMessage($this->msgBotAlreadyExists());
            return;
        } catch (InvalidTokenException) {
            $bot->sendMessage($this->msgInvalidToken());
            return;
        }

        $importedExports = $this->em->getRepository(ChatExportFile::class)->findBy(
            ['owner' => $user, 'isImported' => true],
            ['createdAt' => 'DESC'],
            10
        );

        if (empty($importedExports)) {
            $bot->sendMessage(
                $this->msgExportInstructions(),
                parse_mode: ParseMode::MARKDOWN
            );
            $bot->sendMessage("How would you like to upload the file?", reply_markup: $this->buildUploadMethodKeyboard());
            $this->next('selectUploadMethod');
            return;
        }

        $bot->sendMessage(
            "Great\\! Now choose a previously uploaded chat history or upload a new one 👇",
            parse_mode: ParseMode::MARKDOWN,
            reply_markup: $this->buildExportSelectionKeyboard($importedExports)
        );
        $this->next('selectSource');
    }

    public function selectSource(Nutgram $bot): void
    {
        $botEntity = $this->em->find(Bot::class, $this->botId);
        if (!$botEntity) {
            $bot->sendMessage($this->msgSessionExpired());
            $this->end();
            return;
        }

        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData === 'upload_new_export') {
            $bot->answerCallbackQuery();
            $bot->sendMessage(
                $this->msgExportInstructions(),
                parse_mode: ParseMode::MARKDOWN
            );
            $bot->sendMessage("How would you like to upload the file?", reply_markup: $this->buildUploadMethodKeyboard());
            $this->next('selectUploadMethod');
            return;
        }

        if ($callbackData !== null && str_starts_with($callbackData, 'use_export:')) {
            $fileId = (int)substr($callbackData, strlen('use_export:'));
            $user = $this->userProvider->getOrCreate($bot->userId());
            $file = $this->em->getRepository(ChatExportFile::class)->findOneBy([
                'id' => $fileId,
                'owner' => $user,
            ]);

            if (!$file) {
                $bot->answerCallbackQuery(text: 'File not found.');
                return;
            }

            $bot->answerCallbackQuery();
            $botEntity->setChatExportFile($file);
            $this->em->flush();

            $participants = $this->chatMessageRepository->findDistinctParticipants($file);
            $bot->editMessageReplyMarkup(reply_markup: null);
            $bot->sendMessage(
                "Who should the bot imitate? 👇",
                reply_markup: $this->buildParticipantKeyboard($botEntity->getId(), $participants)
            );
            $this->next('awaitParticipantSelection');
            return;
        }

        // User sent a file directly instead of tapping the keyboard
        if ($bot->message()?->document !== null) {
            $this->handleDocumentUpload($bot, $botEntity);
            return;
        }

        $bot->sendMessage("Please choose one of the options above or send the result.json file directly.");
    }

    public function selectUploadMethod(Nutgram $bot): void
    {
        $botEntity = $this->em->find(Bot::class, $this->botId);
        if (!$botEntity) {
            $bot->sendMessage($this->msgSessionExpired());
            $this->end();
            return;
        }

        // They sent a file directly — skip the keyboard
        if ($bot->message()?->document !== null) {
            $this->handleDocumentUpload($bot, $botEntity);
            return;
        }

        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData === 'upload_via_telegram') {
            $bot->answerCallbackQuery();
            $bot->editMessageReplyMarkup(reply_markup: null);
            $bot->sendMessage("Send me the result.json file 👇");
            $this->next('receiveHistory');
            return;
        }

        if ($callbackData === 'upload_via_web') {
            $bot->answerCallbackQuery();
            $bot->editMessageReplyMarkup(reply_markup: null);
            $uploadUrl = $this->fileHandler->generateUploadLink($botEntity);
            $bot->sendMessage($this->uploadLinkMessage($uploadUrl));
            $this->next('awaitParticipantSelection');
            return;
        }

        $bot->sendMessage("Please choose an upload option above.");
    }

    public function receiveHistory(Nutgram $bot): void
    {
        $botEntity = $this->em->find(Bot::class, $this->botId);
        if (!$botEntity) {
            $bot->sendMessage($this->msgSessionExpired());
            $this->end();
            return;
        }

        $document = $bot->message()?->document;
        if (!$document) {
            $bot->sendMessage($this->msgSendFile());
            return;
        }

        $this->handleDocumentUpload($bot, $botEntity);
    }

    public function awaitParticipantSelection(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData === null || !str_starts_with($callbackData, 'select_target:')) {
            if ($bot->message() !== null) {
                $bot->sendMessage("Please tap a name from the list above to choose who the bot should imitate.");
            }
            return;
        }

        $parts = explode(':', $callbackData, 3);
        if (count($parts) !== 3) {
            $bot->answerCallbackQuery(text: 'Invalid selection.');
            return;
        }

        [, $botId, $fromId] = $parts;

        $botEntity = $this->em->find(Bot::class, (int)$botId);
        if (!$botEntity) {
            $bot->answerCallbackQuery(text: 'Bot not found.');
            $this->end();
            return;
        }

        $file = $botEntity->getChatExportFile();
        if (!$file) {
            $bot->answerCallbackQuery(text: 'No export file linked to this bot.');
            $this->end();
            return;
        }

        $participants = $this->chatMessageRepository->findDistinctParticipants($file);
        $match = array_filter($participants, fn($p) => $p['fromId'] === $fromId);

        if (empty($match)) {
            $bot->answerCallbackQuery(text: 'Invalid selection.');
            return;
        }

        $senderName = array_values($match)[0]['sender'];

        $botEntity->setTargetFromId($fromId);
        $this->botTrainer->train($botEntity);
        $botEntity->setIsTrained(true);
        $botEntity->setIsBeingTrained(false);
        $this->em->flush();

        try {
            $this->webhookRegistrar->register($botEntity);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register bot webhook', [
                'bot_id' => $botEntity->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        $bot->answerCallbackQuery();
        $bot->editMessageReplyMarkup(reply_markup: null);
        $bot->sendMessage(
            "✅ Done\\! Your bot is now imitating *{$senderName}*\\.\n\nAdd it to a group chat and watch it go\\! 🎭",
            parse_mode: ParseMode::MARKDOWN,
        );

        $this->end();
    }

    private function handleDocumentUpload(Nutgram $bot, Bot $botEntity): void
    {
        $user = $this->userProvider->getOrCreate($bot->userId());
        $document = $bot->message()->document;

        try {
            $chatExportFile = $this->fileHandler->handleTelegramUpload($user, $document);
        } catch (FileNotFoundException) {
            $bot->sendMessage($this->msgFileNotFound());
            return;
        } catch (InvalidMimeTypeException) {
            $bot->sendMessage($this->msgInvalidFileType());
            return;
        } catch (FileTooBigException) {
            $uploadUrl = $this->fileHandler->generateUploadLink($botEntity);
            $bot->sendMessage($this->uploadLinkMessage($uploadUrl));
            $this->next('awaitParticipantSelection');
            return;
        }

        $botEntity->setChatExportFile($chatExportFile);
        $botEntity->setIsBeingTrained(true);
        $this->em->flush();

        $bot->sendMessage($this->fileProcessingStarted());

        try {
            $this->messageBus->dispatch(
                new ProcessChatExportMessage($chatExportFile->getId(), $botEntity->getId())
            );
        } catch (\Throwable) {
            $botEntity->setIsBeingTrained(false);
            $this->em->flush();
            $bot->sendMessage($this->msgTrainingFailed());
            $this->end();
            return;
        }

        $this->next('awaitParticipantSelection');
    }

    // How long before a stuck training job is considered crashed and auto-reset
    private const int TRAINING_TIMEOUT_MINUTES = 30;

    private function hasTrainingBot(User $user): bool
    {
        $bot = $this->em->getRepository(Bot::class)
            ->findOneBy(['owner' => $user, 'isBeingTrained' => true]);

        if (!$bot) {
            return false;
        }

        $deadline = new \DateTimeImmutable(sprintf('-%d minutes', self::TRAINING_TIMEOUT_MINUTES));
        if ($bot->getUpdatedAt() < $deadline) {
            // Worker crashed (OOM, fatal error, etc.) — unblock the user
            $bot->setIsBeingTrained(false);
            $bot->setChatExportFile(null);
            $this->em->flush();
            return false;
        }

        return true;
    }

    private function buildUploadMethodKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('📎 Send here via Telegram (< 20 MB)', callback_data: 'upload_via_telegram'))
            ->addRow(InlineKeyboardButton::make('🌐 Upload via web link (any size)', callback_data: 'upload_via_web'));
    }

    /**
     * @param ChatExportFile[] $exports
     */
    private function buildExportSelectionKeyboard(array $exports): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($exports as $export) {
            $label = $export->getChatName() ?? 'Unnamed chat';
            $date = $export->getCreatedAt()?->format('d M Y') ?? '';
            $keyboard->addRow(
                InlineKeyboardButton::make(
                    "📁 {$label} ({$date})",
                    callback_data: 'use_export:' . $export->getId()
                )
            );
        }

        $keyboard->addRow(
            InlineKeyboardButton::make('⬆️ Upload a new file', callback_data: 'upload_new_export')
        );

        return $keyboard;
    }

    /**
     * @param array<int, array{fromId: string, sender: string, messageCount: int}> $participants
     */
    private function buildParticipantKeyboard(int $botId, array $participants): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();

        foreach ($participants as $participant) {
            $shortId = substr(ltrim($participant['fromId'], 'user'), -4);
            $count = number_format((int)$participant['messageCount'], 0, '.', ' ');
            $label = "{$participant['sender']} · {$count} msgs · #{$shortId}";

            $keyboard->addRow(
                InlineKeyboardButton::make(
                    $label,
                    callback_data: 'select_target:' . $botId . ':' . $participant['fromId']
                )
            );
        }

        return $keyboard;
    }

    // Messages

    private function msgPrivateChatOnly(): string
    {
        return 'To add a bot, message me in private chat.';
    }

    private function msgAlreadyTraining(): string
    {
        return "⏳ A bot is currently being set up. Please wait for it to finish.\n\n" .
            "If it's been stuck for more than " . self::TRAINING_TIMEOUT_MINUTES . " minutes, just send /start again and it will be reset automatically.";
    }

    private function msgSetupInstructions(): string
    {
        return
            "Let's set up your friend's clone! Here's what to do:\n\n" .
            "1. Open @BotFather on Telegram\n" .
            "2. Send /newbot\n" .
            "3. Name it after your friend, e.g. \"John Doe\" with username like @john_doe_clone_bot\n" .
            "4. Optionally set their profile picture with /setuserpic — it makes it way more fun in group chats! 😄\n" .
            "5. Copy the token BotFather gives you\n" .
            "6. Paste it here 👇";
    }

    private function msgBotAlreadyExists(): string
    {
        return
            "❌ This bot is already connected to another account.\n\n" .
            "If you own this bot, please remove it from the other account first or create a new bot with @BotFather.";
    }

    private function msgInvalidToken(): string
    {
        return
            "❌ Invalid token. Please check that you copied it correctly from @BotFather and try again.\n\n" .
            "The token should look like: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz";
    }

    private function msgExportInstructions(): string
    {
        return
            "*To make your clone sound more like your friend, you can feed it their chat history\\!*\n\n" .
            "Here's how to export it:\n\n" .
            "1\\. Open the chat with your friends in *Telegram Desktop*\n" .
            "2\\. Click the *⋮ menu* in the top right\n" .
            "3\\. Select *Export chat history*\n" .
            "4\\. Uncheck everything except *Text messages* — no photos, videos, files or stickers\n" .
            "5\\. Set format to *JSON*\n" .
            "6\\. Click *Export*\n\n" .
            "Once done, send the exported `result\\.json` file here 👇\n\n" .
            "💡 *Tips:*\n" .
            "• The more messages the export contains, the better your clone will sound\n" .
            "• Ask the oldest admin of the chat to do the export — people added later may be missing earlier messages\n" .
            "• For group chats you can reuse the same exported file to train multiple clones\n\n" .
            "⚠️ _Don't worry — the data stays between you and your clone and is only used to shape its personality\\._";
    }

    private function msgSessionExpired(): string
    {
        return "❌ Session expired. Please start over with /start";
    }

    private function msgSendFile(): string
    {
        return "Please send the result.json file.";
    }

    private function msgFileNotFound(): string
    {
        return "File not found. Please try again.";
    }

    private function msgInvalidFileType(): string
    {
        return "Invalid file type. Please send a JSON file.";
    }

    private function fileProcessingStarted(): string
    {
        return "⏳ Processing the file... This may take a few minutes. I'll let you know when it's ready!";
    }

    private function msgTrainingFailed(): string
    {
        return "Could not process chat export file. Please try again later.";
    }

    private function uploadLinkMessage(string $uploadUrl): string
    {
        return "📁 Your file is too large for Telegram.\n\n" .
            "Click here to upload via web instead:\n" .
            $uploadUrl .
            "\n\nOnce uploaded I'll ask you who the bot should imitate.";
    }
}
