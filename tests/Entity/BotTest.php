<?php

namespace App\Tests\Entity;

use App\Entity\Bot;
use App\Enum\ResponseMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BotTest extends TestCase
{
    // ── Float setter clamping ─────────────────────────────────────────────────

    #[Test]
    #[DataProvider('floatClampProvider')]
    public function floatSettersClampToZeroOneRange(string $setter, string $getter, float $input, float $expected): void
    {
        $bot = new Bot();
        $bot->$setter($input);
        $this->assertSame($expected, $bot->$getter());
    }

    public static function floatClampProvider(): array
    {
        $fields = [
            ['setResponseProbability', 'getResponseProbability'],
            ['setMinSimilarity', 'getMinSimilarity'],
            ['setDirectResponseProbability', 'getDirectResponseProbability'],
            ['setSequentialWeight', 'getSequentialWeight'],
            ['setFtsWeight', 'getFtsWeight'],
        ];

        $cases = [];
        foreach ($fields as [$setter, $getter]) {
            $short = str_replace(['set', 'get', 'Probability', 'Weight', 'Similarity'], '', $setter);
            $cases["{$short} above 1.0"] = [$setter, $getter, 1.5, 1.0];
            $cases["{$short} below 0.0"] = [$setter, $getter, -0.5, 0.0];
            $cases["{$short} valid mid"] = [$setter, $getter, 0.4, 0.4];
        }

        return $cases;
    }

    // ── matchLimit clamping ───────────────────────────────────────────────────

    #[Test]
    public function matchLimitClampsToMin1(): void
    {
        $bot = new Bot();
        $bot->setMatchLimit(0);
        $this->assertSame(1, $bot->getMatchLimit());
    }

    #[Test]
    public function matchLimitClampsToMax50(): void
    {
        $bot = new Bot();
        $bot->setMatchLimit(999);
        $this->assertSame(50, $bot->getMatchLimit());
    }

    #[Test]
    public function matchLimitAcceptsValidValue(): void
    {
        $bot = new Bot();
        $bot->setMatchLimit(10);
        $this->assertSame(10, $bot->getMatchLimit());
    }

    // ── Defaults ─────────────────────────────────────────────────────────────

    #[Test]
    public function defaultsAreCorrect(): void
    {
        $bot = new Bot();

        $this->assertSame(1.0, $bot->getResponseProbability());
        $this->assertSame(0.1, $bot->getMinSimilarity());
        $this->assertSame(0.5, $bot->getDirectResponseProbability());
        $this->assertSame(0.3, $bot->getSequentialWeight());
        $this->assertSame(0.0, $bot->getFtsWeight());
        $this->assertSame(5, $bot->getMatchLimit());
        $this->assertSame(ResponseMode::Direct, $bot->getResponseMode());
        $this->assertFalse($bot->isDebugMode());
        $this->assertNull($bot->getWebhookSecret());
    }

    // ── ResponseMode ─────────────────────────────────────────────────────────

    #[Test]
    public function responseModeCanBeSetToHybrid(): void
    {
        $bot = new Bot();
        $bot->setResponseMode(ResponseMode::Hybrid);
        $this->assertSame(ResponseMode::Hybrid, $bot->getResponseMode());
    }

    // ── Debug mode ────────────────────────────────────────────────────────────

    #[Test]
    public function debugModeDefaultsToFalse(): void
    {
        $this->assertFalse((new Bot())->isDebugMode());
    }

    #[Test]
    public function debugModeCanBeEnabled(): void
    {
        $bot = new Bot();
        $bot->setDebugMode(true);
        $this->assertTrue($bot->isDebugMode());
    }

    // ── Webhook secret ────────────────────────────────────────────────────────

    #[Test]
    public function webhookSecretCanBeSetAndRetrieved(): void
    {
        $bot = new Bot();
        $bot->setWebhookSecret('abc123');
        $this->assertSame('abc123', $bot->getWebhookSecret());
    }

    #[Test]
    public function webhookSecretCanBeCleared(): void
    {
        $bot = new Bot();
        $bot->setWebhookSecret('abc123');
        $bot->setWebhookSecret(null);
        $this->assertNull($bot->getWebhookSecret());
    }
}
