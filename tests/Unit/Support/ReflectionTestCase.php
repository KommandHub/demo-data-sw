<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

abstract class ReflectionTestCase extends TestCase
{
    /**
     * @param array<int, mixed> $arguments
     */
    protected function invoke(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $methodReflection = $reflection->getMethod($method);

        return $methodReflection->invokeArgs($object, $arguments);
    }
}
