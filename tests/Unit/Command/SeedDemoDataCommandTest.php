<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Command;

use Kommandhub\DemoDataSW\Command\SeedDemoDataCommand;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class SeedDemoDataCommandTest extends ReflectionTestCase
{
    public function testExecuteRunsCommandsInSequence(): void
    {
        $first = $this->createMock(Command::class);
        $first->expects(self::once())->method('run')->willReturn(Command::SUCCESS);

        $second = $this->createMock(Command::class);
        $second->expects(self::once())->method('run')->willReturn(Command::SUCCESS);

        $third = $this->createMock(Command::class);
        $third->expects(self::once())->method('run')->willReturn(Command::SUCCESS);

        $fourth = $this->createMock(Command::class);
        $fourth->expects(self::once())->method('run')->willReturn(Command::SUCCESS);

        $application = $this->createMock(Application::class);
        $application->expects(self::exactly(4))
            ->method('find')
            ->willReturnMap([
                ['kommandhub:add-main-categories', $first],
                ['kommandhub:add-footer-categories', $second],
                ['kommandhub:add-property-groups', $third],
                ['kommandhub:add-demo-products', $fourth],
            ]);

        $command = new SeedDemoDataCommand();
        $command->setApplication($application);

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    public function testExecuteStopsWhenACommandFails(): void
    {
        $first = $this->createMock(Command::class);
        $first->expects(self::once())->method('run')->willReturn(Command::SUCCESS);

        $second = $this->createMock(Command::class);
        $second->expects(self::once())->method('run')->willReturn(Command::FAILURE);

        $third = $this->createMock(Command::class);
        $third->expects(self::never())->method('run');

        $application = $this->createMock(Application::class);
        $application->expects(self::exactly(2))
            ->method('find')
            ->willReturnCallback(static function (string $name) use ($first, $second, $third): Command {
                return match ($name) {
                    'kommandhub:add-main-categories' => $first,
                    'kommandhub:add-footer-categories' => $second,
                    'kommandhub:add-property-groups' => $third,
                    default => throw new \InvalidArgumentException($name),
                };
            });

        $command = new SeedDemoDataCommand();
        $command->setApplication($application);

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenApplicationIsNull(): void
    {
        $command = new SeedDemoDataCommand();
        // Application is null by default

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::FAILURE, $status);
    }
}
