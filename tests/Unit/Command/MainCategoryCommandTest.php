<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Command;

use Kommandhub\DemoDataSW\Command\MainCategoryCommand;
use Shopware\Core\Content\Category\CategoryEntity;
use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntity;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntityCollection;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MainCategoryCommandTest extends ReflectionTestCase
{
    public function testPrepareCategoriesAssignsCmsPageIdRecursively(): void
    {
        $command = new MainCategoryCommand(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            new EntityChoiceSelector()
        );

        $payload = $this->invoke($command, 'prepareCategories', [
            [
                [
                    'name' => 'Parent',
                    'children' => [
                        ['name' => 'Child'],
                    ],
                ],
            ],
            'cms-page-id',
        ]);

        self::assertCount(1, $payload);
        self::assertSame('cms-page-id', $payload[0]['cmsPageId']);
        self::assertSame('cms-page-id', $payload[0]['children'][0]['cmsPageId']);
    }

    public function testExecuteReturnsFailureWhenNoCategoriesFound(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn(
            $this->createSearchResult([])
        );

        $command = new MainCategoryCommand(
            $categoryRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityChoiceSelector::class)
        );

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenNoParentCategorySelected(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn(
            $this->createSearchResult([new DemoEntity('1', 'Root')])
        );

        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')->willReturn(null);

        $command = new MainCategoryCommand(
            $categoryRepository,
            $this->createMock(EntityRepository::class),
            $selector
        );

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenNoCmsPageFound(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn(
            $this->createSearchResult([new DemoEntity('1', 'Root')])
        );

        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')
            ->willReturnOnConsecutiveCalls(
                ['id' => '1', 'name' => 'Root'],
                null
            );

        $command = new MainCategoryCommand(
            $categoryRepository,
            $this->createMock(EntityRepository::class),
            $selector
        );

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsSuccess(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn(
            $this->createSearchResult([new DemoEntity('1', 'Root')])
        );
        $categoryRepository->expects(self::once())->method('upsert');

        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')
            ->willReturnCallback(function ($io, $repo, $context, $criteria, $prompt, $callback) {
                $entity = $this->createMock(CategoryEntity::class);
                $entity->method('getBreadcrumb')->willReturn(['Root']);
                $entity->method('getName')->willReturn('Root');

                self::assertNotNull($callback, 'Callback passed to selectOne should not be null.');

                if (\is_callable($callback)) {
                    $callback($entity); // Execute callback for coverage
                }

                static $callCount = 0;
                $returns = [
                    ['id' => '1', 'name' => 'Root'],
                    ['id' => 'cms-1', 'name' => 'CMS Page'],
                ];

                return $returns[$callCount++] ?? null;
            });

        $command = new MainCategoryCommand(
            $categoryRepository,
            $this->createMock(EntityRepository::class),
            $selector
        );

        $status = $this->invoke($command, 'execute', [
            new ArrayInput([]),
            new BufferedOutput(),
        ]);

        self::assertSame(Command::SUCCESS, $status);
    }

    /**
     * @param array<int, DemoEntity> $entities
     */
    private function createSearchResult(array $entities): EntitySearchResult
    {
        $collection = new DemoEntityCollection($entities);

        return new EntitySearchResult(
            'category',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
