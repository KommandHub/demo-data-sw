<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Command;

use Kommandhub\DemoDataSW\Command\PropertyGroupCommand;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class PropertyGroupCommandTest extends ReflectionTestCase
{
    public function testExecuteSeedsAllPropertyGroupsAndOptions(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('upsert')
            ->with(self::callback(function (array $payload): bool {
                self::assertCount(20, $payload);
                self::assertSame('Color', $payload[0]['name']);
                self::assertArrayHasKey('options', $payload[0]);
                self::assertGreaterThan(0, count($payload[0]['options']));

                return true;
            }), self::isInstanceOf(Context::class));

        $command = new PropertyGroupCommand($repository);

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(PropertyGroupCommand::SUCCESS, $status);
    }

    public function testExecuteReturnsFailureOnException(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('upsert')->willThrowException(new \Exception('Upsert failed'));

        $command = new PropertyGroupCommand($repository);

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(PropertyGroupCommand::FAILURE, $status);
    }
}
