<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs all demo seeding commands in sequence:
 * 1. Categories
 * 2. Property groups
 * 3. Products
 */
#[AsCommand('kommandhub:seed-demo-data', 'Run category, property group, and product demo seed commands')]
class SeedDemoDataCommand extends Command
{
    /**
     * @var array<int, string>
     */
    private const COMMAND_SEQUENCE = [
        'kommandhub:add-main-categories',
        'kommandhub:add-footer-categories',
        'kommandhub:add-property-groups',
        'kommandhub:add-demo-products',
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();

        if ($application === null) {
            $io->error('Console application is not available.');

            return Command::FAILURE;
        }

        $io->title('Running Kommandhub demo data seeding');

        foreach (self::COMMAND_SEQUENCE as $commandName) {
            $io->section(\sprintf('Running %s', $commandName));

            $command = $application->find($commandName);
            $statusCode = $command->run($input, $output);

            if ($statusCode !== Command::SUCCESS) {
                $io->error(\sprintf('Command failed: %s', $commandName));

                return Command::FAILURE;
            }
        }

        $io->success('All demo data commands completed successfully.');

        return Command::SUCCESS;
    }
}
