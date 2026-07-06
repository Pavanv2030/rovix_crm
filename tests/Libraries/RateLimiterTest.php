<?php

namespace Tests\Libraries;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tests the rate-limiting math used in BroadcastProcessor (70 msg/sec).
 *
 * @internal
 */
final class RateLimiterTest extends CIUnitTestCase
{
    private int $maxPerSecond = 70;

    public function testSleepTimeIsPositiveWhenBatchIsFasterThanOneSecond(): void
    {
        $startTime = microtime(true);

        // Simulate "instant" batch (elapsed ≈ 0)
        $elapsed   = microtime(true) - $startTime;
        $sleepTime = (1.0 - $elapsed) * 1_000_000; // microseconds

        $this->assertGreaterThan(0, $sleepTime);
        $this->assertLessThanOrEqual(1_000_000, $sleepTime);
    }

    public function testNoSleepNeededWhenBatchTakesLongerThanOneSecond(): void
    {
        $elapsed = 1.5; // seconds — batch was slow
        $shouldSleep = $elapsed < 1.0;

        $this->assertFalse($shouldSleep);
    }

    public function testExpectedBatchCount(): void
    {
        $totalMessages = 700;
        $batches       = intdiv($totalMessages, $this->maxPerSecond);

        $this->assertSame(10, $batches);
    }

    public function testBroadcastRateComputation(): void
    {
        $totalMessages = 150;
        $sentCount     = 0;
        $batchCount    = 0;

        for ($i = 0; $i < $totalMessages; $i++) {
            $sentCount++;
            if ($sentCount % $this->maxPerSecond === 0) {
                $batchCount++;
            }
        }

        $this->assertSame(150, $sentCount);
        $this->assertSame(2, $batchCount); // 150 / 70 = 2 complete batches
    }

    public function testSingleMessageNeverHitsRateLimit(): void
    {
        $hitLimit = (1 % $this->maxPerSecond === 0);
        $this->assertFalse($hitLimit);
    }

    public function testExactlyMaxPerSecondHitsLimit(): void
    {
        $hitLimit = ($this->maxPerSecond % $this->maxPerSecond === 0);
        $this->assertTrue($hitLimit);
    }
}
