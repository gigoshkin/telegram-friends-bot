<?php

namespace App\Tests\Service\BotResponder;

use App\Entity\Bot;
use App\Entity\ChatExportFile;
use App\Enum\ResponseMode;
use App\Repository\ChatMessageRepository;
use App\Service\BotResponder\TriggramBotResponder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class TriggramBotResponderTest extends TestCase
{
    private ChatMessageRepository&Stub $repo;
    private TriggramBotResponder $responder;

    protected function setUp(): void
    {
        $this->repo = $this->createStub(ChatMessageRepository::class);
        $this->responder = new TriggramBotResponder($this->repo);
    }

    /** Replaces the stub with a mock for tests that need call expectations. */
    private function mockRepo(): ChatMessageRepository&MockObject
    {
        $mock = $this->createMock(ChatMessageRepository::class);
        $this->responder = new TriggramBotResponder($mock);
        return $mock;
    }

    // ── Precondition guards ───────────────────────────────────────────────────

    #[Test]
    public function returnsNullWhenBotHasNoExportFile(): void
    {
        $bot = new Bot();
        $bot->setTargetFromId('user1');

        $repo = $this->mockRepo();
        $repo->expects($this->never())->method('findBestReplyPairs');

        $this->assertNull($this->responder->respond($bot, 'hello'));
    }

    #[Test]
    public function returnsNullWhenBotHasNoTarget(): void
    {
        $bot = new Bot();
        $bot->setChatExportFile($this->makeFile());

        $repo = $this->mockRepo();
        $repo->expects($this->never())->method('findBestReplyPairs');

        $this->assertNull($this->responder->respond($bot, 'hello'));
    }

    #[Test]
    public function returnsNullWhenRepositoryFindsNoPairs(): void
    {
        $this->repo->method('findBestReplyPairs')->willReturn([]);

        $this->assertNull($this->responder->respond($this->makeBot(), 'hello there'));
    }

    // ── Normal response ───────────────────────────────────────────────────────

    #[Test]
    public function returnsReplyTextFromPair(): void
    {
        $this->repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => 'hello there', 'reply_text' => 'hey!', 'score' => 0.8],
        ]);

        $this->assertSame('hey!', $this->responder->respond($this->makeBot(), 'hello there'));
    }

    // ── @mention stripping ────────────────────────────────────────────────────

    #[Test]
    public function stripsAtMentionBeforeQuerying(): void
    {
        $repo = $this->mockRepo();
        $repo->expects($this->once())
            ->method('findBestReplyPairs')
            ->with($this->anything(), $this->anything(), 'are you coming tonight?')
            ->willReturn([['trigger_text' => 'are you coming?', 'reply_text' => 'yes!', 'score' => 0.9]]);

        $this->responder->respond($this->makeBot(), '@john are you coming tonight?');
    }

    #[Test]
    public function stripsMultipleAtMentions(): void
    {
        $repo = $this->mockRepo();
        $repo->expects($this->once())
            ->method('findBestReplyPairs')
            ->with($this->anything(), $this->anything(), 'what do you think?')
            ->willReturn([['trigger_text' => 'opinion?', 'reply_text' => 'idk', 'score' => 0.5]]);

        $this->responder->respond($this->makeBot(), '@alice @bob what do you think?');
    }

    #[Test]
    public function fallsBackToOriginalWhenOnlyMentions(): void
    {
        $repo = $this->mockRepo();
        $repo->expects($this->once())
            ->method('findBestReplyPairs')
            ->with($this->anything(), $this->anything(), '@john')
            ->willReturn([]);

        $this->responder->respond($this->makeBot(), '@john');
    }

    // ── Dynamic min similarity ────────────────────────────────────────────────

    #[Test]
    #[DataProvider('dynamicSimilarityProvider')]
    public function appliesDynamicMinSimilarityFloor(string $message, float $expectedMin): void
    {
        $bot = $this->makeBot();
        $bot->setMinSimilarity(0.05); // very low configured floor

        $repo = $this->mockRepo();
        $repo->expects($this->once())
            ->method('findBestReplyPairs')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $expectedMin,
            )
            ->willReturn([]);

        $this->responder->respond($bot, $message);
    }

    public static function dynamicSimilarityProvider(): array
    {
        return [
            '1 word → 0.7'              => ['ok', 0.7],
            '2 words → 0.5'             => ['ok sure', 0.5],
            '3 words → 0.3'             => ['are you coming', 0.3],
            '4 words → 0.3'             => ['are you coming tonight', 0.3],
            '5 words → 0.2'             => ['did you see that movie', 0.2],
            '7 words → 0.2'             => ['did you see that movie last night', 0.2],
            '8 words → configured floor' => ['did you see that movie last night please', 0.05],
        ];
    }

    #[Test]
    public function configuredFloorOverridesDynamicWhenHigher(): void
    {
        $bot = $this->makeBot();
        $bot->setMinSimilarity(0.9); // very strict configured floor

        // 1-word dynamic floor is 0.7, but configured is 0.9 → max(0.9, 0.7) = 0.9
        $repo = $this->mockRepo();
        $repo->expects($this->once())
            ->method('findBestReplyPairs')
            ->with($this->anything(), $this->anything(), $this->anything(), $this->anything(), 0.9)
            ->willReturn([]);

        $this->responder->respond($bot, 'ok');
    }

    // ── Response mode ─────────────────────────────────────────────────────────

    #[Test]
    public function doesNotCallSequentialPairsInDirectMode(): void
    {
        $bot = $this->makeBot();
        $bot->setResponseMode(ResponseMode::Direct);

        $repo = $this->mockRepo();
        $repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => 'hi', 'reply_text' => 'hello', 'score' => 0.8],
        ]);
        $repo->expects($this->never())->method('findSequentialPairs');

        $this->responder->respond($bot, 'hi');
    }

    #[Test]
    public function callsSequentialPairsInHybridMode(): void
    {
        $bot = $this->makeBot();
        $bot->setResponseMode(ResponseMode::Hybrid);

        $repo = $this->mockRepo();
        $repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => 'hi', 'reply_text' => 'hello', 'score' => 0.8],
        ]);
        $repo->expects($this->once())->method('findSequentialPairs')->willReturn([]);

        $this->responder->respond($bot, 'hi');
    }

    #[Test]
    public function returnsNullWhenBothPoolsEmptyInHybridMode(): void
    {
        $bot = $this->makeBot();
        $bot->setResponseMode(ResponseMode::Hybrid);

        $this->repo->method('findBestReplyPairs')->willReturn([]);
        $this->repo->method('findSequentialPairs')->willReturn([]);

        $this->assertNull($this->responder->respond($bot, 'hello there'));
    }

    #[Test]
    public function picksFromSeqPoolWhenDirectIsEmpty(): void
    {
        $bot = $this->makeBot();
        $bot->setResponseMode(ResponseMode::Hybrid);

        $this->repo->method('findBestReplyPairs')->willReturn([]);
        $this->repo->method('findSequentialPairs')->willReturn([
            ['trigger_text' => 'hey', 'reply_text' => 'sup', 'score' => 0.6],
        ]);

        $this->assertSame('sup', $this->responder->respond($bot, 'hello there'));
    }

    // ── Debug mode ────────────────────────────────────────────────────────────

    #[Test]
    public function debugModeIncludesTriggerAndScore(): void
    {
        $bot = $this->makeBot();
        $bot->setDebugMode(true);

        $this->repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => 'are you coming?', 'reply_text' => 'yes!', 'score' => 0.85],
        ]);

        $result = $this->responder->respond($bot, 'are you coming tonight?');

        $this->assertStringContainsString('are you coming?', $result);
        $this->assertStringContainsString('yes!', $result);
        $this->assertStringContainsString('0.85', $result);
        $this->assertStringContainsString('direct', $result);
    }

    #[Test]
    public function debugModeEscapesTriggerHtml(): void
    {
        $bot = $this->makeBot();
        $bot->setDebugMode(true);

        $this->repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => '<script>alert(1)</script>', 'reply_text' => 'reply', 'score' => 0.9],
        ]);

        $result = $this->responder->respond($bot, 'something');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function debugModeShowsTimingForHybridMode(): void
    {
        $bot = $this->makeBot();
        $bot->setDebugMode(true);
        $bot->setResponseMode(ResponseMode::Hybrid);

        $this->repo->method('findBestReplyPairs')->willReturn([
            ['trigger_text' => 'trigger', 'reply_text' => 'reply', 'score' => 0.7],
        ]);
        $this->repo->method('findSequentialPairs')->willReturn([]);

        $result = $this->responder->respond($bot, 'trigger message here');

        $this->assertStringContainsString('seq:', $result);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeFile(): ChatExportFile
    {
        $file = $this->createStub(ChatExportFile::class);
        $file->method('getId')->willReturn(42);
        return $file;
    }

    private function makeBot(): Bot
    {
        $bot = new Bot();
        $bot->setChatExportFile($this->makeFile());
        $bot->setTargetFromId('user123');
        $bot->setResponseProbability(1.0);
        return $bot;
    }
}
