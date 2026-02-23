<?php

namespace App\Telegram\Conversation;

use App\Entity\Bot;
use App\Exception\Service\BotFactory\BotAlreadyExistsException;
use App\Exception\Service\BotFactory\InvalidTokenException;
use App\Exception\Service\ChatExportFileHandler\FileNotFoundException;
use App\Exception\Service\ChatExportFileHandler\InvalidMimeTypeException;
use App\Message\ProcessChatExportMessage;
use App\Service\BotFactory;
use App\Service\ChatExportFileHandler;
use App\Service\TelegramUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ChatType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Symfony\Component\Messenger\MessageBusInterface;

class AddBotConversation extends Conversation
{
    private ?int $botId = null;

    public function __construct(
        private readonly TelegramUserProvider   $userProvider,
        private readonly BotFactory             $botFactory,
        private readonly MessageBusInterface    $messageBus,
        private readonly EntityManagerInterface $em,
        private readonly ChatExportFileHandler  $fileHandler
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

        if ($bot->chat()->type !== ChatType::PRIVATE) {
            $bot->sendMessage($this->msgPrivateChatOnly());
            $this->end();
            return;
        }

        if ($this->hasTrainingBot($userId)) {
            $bot->sendMessage($this->msgAlreadyTraining());
            $this->end();
            return;
        }

        $bot->sendMessage($this->msgSetupInstructions());
        $this->next('receiveToken');
    }

    public function receiveToken(Nutgram $bot): void
    {
        $userId = $bot->userId();
        $token = trim($bot->message()->text);
        $user = $this->userProvider->getOrCreate($userId);

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

        $bot->sendMessage(
            $this->msgExportInstructions(),
            parse_mode: ParseMode::MARKDOWN
        );

        $this->next('receiveHistory');
    }

    public function receiveHistory(Nutgram $bot): void
    {
        $botEntity = $this->em->find(Bot::class, $this->botId);
        if (!$botEntity) {
            $bot->sendMessage($this->msgSessionExpired());
            $this->end();
            return;
        }

        $userId = $bot->userId();
        $user = $this->userProvider->getOrCreate($userId);
        $document = $bot->message()->document;

        if (!$document) {
            $bot->sendMessage($this->msgSendFile());
            return;
        }

        try {
            $chatExportFile = $this->fileHandler->saveFile($user, $document);
        } catch (FileNotFoundException) {
            $bot->sendMessage($this->msgFileNotFound());
            return;
        } catch (InvalidMimeTypeException) {
            $bot->sendMessage($this->msgInvalidFileType());
            return;
        }

        $bot->sendMessage($this->msgTrainingStarted());

        $botEntity->setIsBeingTrained(true);
        $this->em->flush();

        $trainMessage = new ProcessChatExportMessage($chatExportFile->getId(), $botEntity->getId());
        try {
            $this->messageBus->dispatch($trainMessage);
        } catch (\Throwable) {
            $botEntity->setIsBeingTrained(false);
            $this->em->flush();
            $bot->sendMessage($this->msgTrainingFailed());
        }

        $this->end();
    }

    private function hasTrainingBot(int $userId): bool
    {
        $trainedBot = $this->em->getRepository(Bot::class)
            ->findOneBy([
                'ownerId' => $userId,
                'isBeingTrained' => true
            ]);

        return $trainedBot instanceof Bot;
    }

    // Messages

    private function msgPrivateChatOnly(): string
    {
        return 'To add a bot, message me in private chat.';
    }

    private function msgAlreadyTraining(): string
    {
        return 'You already have a bot in training. Please wait for the process to finish.';
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

    private function msgTrainingStarted(): string
    {
        return "⏳ Training your bot... This may take a few minutes. I'll let you know when it's ready!";
    }

    private function msgTrainingFailed(): string
    {
        return "Could not process chat export file. Please try again later.";
    }
}
