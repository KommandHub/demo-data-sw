<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Service;

use Kommandhub\DemoDataSW\Service\DeterministicValueGenerator;
use PHPUnit\Framework\TestCase;

final class DeterministicValueGeneratorTest extends TestCase
{
    public function testIntIsDeterministicAndWithinBounds(): void
    {
        $generator = new DeterministicValueGenerator();

        $first = $generator->int('demo-key', 10, 20);
        $second = $generator->int('demo-key', 10, 20);

        self::assertSame($first, $second);
        self::assertGreaterThanOrEqual(10, $first);
        self::assertLessThanOrEqual(20, $first);
    }

    public function testFloatIsDeterministicAndRounded(): void
    {
        $generator = new DeterministicValueGenerator();

        $first = $generator->float('demo-key', 1.25, 9.75, 2);
        $second = $generator->float('demo-key', 1.25, 9.75, 2);

        self::assertSame($first, $second);
        self::assertGreaterThanOrEqual(1.25, $first);
        self::assertLessThanOrEqual(9.75, $first);
        self::assertSame(round($first, 2), $first);
    }

    public function testMinimumWinsWhenRangeIsInvalid(): void
    {
        $generator = new DeterministicValueGenerator();

        self::assertSame(5, $generator->int('demo-key', 5, 2));
        self::assertSame(3.14, $generator->float('demo-key', 3.14, 1.0, 2));
    }
}
