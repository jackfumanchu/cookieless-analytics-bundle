<?php

declare(strict_types=1);

namespace Jackfumanchu\CookielessAnalyticsBundle\Tests\Unit\Service;

use Jackfumanchu\CookielessAnalyticsBundle\Service\FingerprintGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FingerprintGeneratorTest extends TestCase
{
    private FingerprintGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FingerprintGenerator();
    }

    #[Test]
    public function generate_returns_64_char_hex_string(): void
    {
        $result = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-10'));

        self::assertSame(64, strlen($result));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result);
    }

    #[Test]
    public function generate_is_deterministic(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);

        self::assertSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_date(): void
    {
        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-10'));
        $result2 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', new \DateTimeImmutable('2026-04-11'));

        self::assertNotSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_ip(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('192.168.1.1', 'Mozilla/5.0', $date);

        self::assertNotSame($result1, $result2);
    }

    #[Test]
    public function generate_returns_different_hash_for_different_user_agent(): void
    {
        $date = new \DateTimeImmutable('2026-04-10');

        $result1 = $this->generator->generate('127.0.0.1', 'Mozilla/5.0', $date);
        $result2 = $this->generator->generate('127.0.0.1', 'Chrome/120', $date);

        self::assertNotSame($result1, $result2);
    }
}
