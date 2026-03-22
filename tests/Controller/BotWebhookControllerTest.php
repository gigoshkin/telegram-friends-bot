<?php

namespace App\Tests\Controller;

use App\Controller\BotWebhookController;
use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Repository\BotRepository;
use App\Service\BotResponder\BotResponderInterface;
use App\Service\TokenEncryptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class BotWebhookControllerTest extends TestCase
{
    private function makeController(
        ?BotRepository $repo = null,
        ?BotResponderInterface $responder = null,
    ): BotWebhookController {
        return new BotWebhookController(
            $repo ?? $this->createStub(BotRepository::class),
            $responder ?? $this->createStub(BotResponderInterface::class),
            $this->createStub(TokenEncryptionService::class),
            new NullLogger(),
        );
    }

    #[Test]
    public function returns200WhenBotNotFound(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $response = $this->makeController($repo)->handle('unknown_id', $this->makeRequest('hello'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    #[Test]
    public function returns200WhenWebhookSecretMismatch(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot(secret: 'correct-secret'));

        $responder = $this->createMock(BotResponderInterface::class);
        $responder->expects($this->never())->method('respond');

        $request = $this->makeRequest('hello', secret: 'wrong-secret');
        $response = $this->makeController($repo, $responder)->handle('123', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns200WhenNoSecretHeaderOnSecuredBot(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot(secret: 'correct-secret'));

        $responder = $this->createMock(BotResponderInterface::class);
        $responder->expects($this->never())->method('respond');

        $response = $this->makeController($repo, $responder)->handle('123', $this->makeRequest('hello'));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function passesWithCorrectSecret(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot(secret: 'correct-secret'));

        $responder = $this->createStub(BotResponderInterface::class);
        $responder->method('respond')->willReturn(null);

        $request = $this->makeRequest('hello', secret: 'correct-secret');
        $response = $this->makeController($repo, $responder)->handle('123', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function passesWhenBotHasNoSecret(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot(secret: null));

        $responder = $this->createStub(BotResponderInterface::class);
        $responder->method('respond')->willReturn(null);

        $response = $this->makeController($repo, $responder)->handle('123', $this->makeRequest('hello'));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns200WhenMessageTextIsMissing(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot());

        $responder = $this->createMock(BotResponderInterface::class);
        $responder->expects($this->never())->method('respond');

        $payload = json_encode(['message' => ['chat' => ['id' => 1]]]);
        $response = $this->makeController($repo, $responder)->handle('123', Request::create('/', 'POST', content: $payload));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function returns200WhenChatIdIsMissing(): void
    {
        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($this->makeTrainedBot());

        $responder = $this->createMock(BotResponderInterface::class);
        $responder->expects($this->never())->method('respond');

        $payload = json_encode(['message' => ['text' => 'hello']]);
        $response = $this->makeController($repo, $responder)->handle('123', Request::create('/', 'POST', content: $payload));

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function callsResponderWithMessageText(): void
    {
        $bot = $this->makeTrainedBot();

        $repo = $this->createStub(BotRepository::class);
        $repo->method('findOneBy')->willReturn($bot);

        $responder = $this->createMock(BotResponderInterface::class);
        $responder->expects($this->once())
            ->method('respond')
            ->with($bot, 'hello world')
            ->willReturn(null);

        $this->makeController($repo, $responder)->handle('123', $this->makeRequest('hello world'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTrainedBot(?string $secret = null): Bot
    {
        $bot = new Bot();
        $bot->setChatExportFile($this->createStub(ChatExportFile::class));
        $bot->setTargetFromId('user1');
        $bot->setIsTrained(true);
        $bot->setWebhookSecret($secret);
        return $bot;
    }

    private function makeRequest(string $text, ?string $secret = null): Request
    {
        $payload = json_encode([
            'message' => [
                'message_id' => 1,
                'text' => $text,
                'chat' => ['id' => 100],
            ],
        ]);

        $request = Request::create('/', 'POST', content: $payload);

        if ($secret !== null) {
            $request->headers->set('X-Telegram-Bot-Api-Secret-Token', $secret);
        }

        return $request;
    }
}
