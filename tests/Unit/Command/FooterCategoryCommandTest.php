<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Command;

use Kommandhub\DemoDataSW\Command\FooterCategoryCommand;
use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntity;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntityCollection;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class FooterCategoryCommandTest extends ReflectionTestCase
{
    public function testPrepareCategoriesAssignsDeterministicIdsRecursively(): void
    {
        $command = new FooterCategoryCommand(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityChoiceSelector::class)
        );

        $payload = $this->invoke($command, 'prepareCategories', [[
            [
                'name' => 'ShopHub',
                'children' => [
                    ['name' => 'About ShopHub'],
                ],
            ],
        ]]);

        self::assertCount(1, $payload);
        self::assertSame(Uuid::fromStringToHex('ShopHub'), $payload[0]['id']);
        self::assertSame(Uuid::fromStringToHex('About ShopHub'), $payload[0]['children'][0]['id']);
    }

    public function testPrepareCategoriesRespectsActiveAndVisibleFlags(): void
    {
        $command = new FooterCategoryCommand(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityChoiceSelector::class)
        );

        $payload = $this->invoke($command, 'prepareCategories', [[
            [
                'name' => 'Active Item',
                'active' => true,
                'visible' => true,
            ],
            [
                'name' => 'Inactive Item',
                'active' => false,
                'visible' => false,
            ],
        ]]);

        self::assertCount(2, $payload);
        self::assertTrue($payload[0]['active']);
        self::assertTrue($payload[0]['visible']);
        self::assertFalse($payload[1]['active']);
        self::assertFalse($payload[1]['visible']);
    }

    public function testExecuteUsesCorrectBreadcrumbCallback(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $searchResult = $this->createSearchResult([new DemoEntity('root-id', 'My Footer')]);
        $categoryRepository->method('search')->willReturn($searchResult);

        $selector = $this->createMock(EntityChoiceSelector::class);
        $callback = null;
        $selector->expects(self::once())
            ->method('selectOne')
            ->willReturnCallback(function ($io, $repository, $context, $criteria, $prompt, $cb) use (&$callback) {
                if (\is_callable($cb)) {
                    $callback = $cb;
                }

                return ['id' => 'root-id', 'name' => 'My Footer'];
            });

        $command = new FooterCategoryCommand($categoryRepository, $selector);

        $input = new ArrayInput(['--no-interaction' => false]);
        $input->setStream(fopen('php://memory', 'r+', false));
        fwrite($input->getStream(), "y\n");
        rewind($input->getStream());

        $this->invoke($command, 'execute', [$input, new BufferedOutput()]);

        self::assertNotNull($callback);

        $category = new DemoEntity('cat-id', 'Category Name');
        $category->setBreadcrumb(['Parent', 'Category Name']);

        self::assertNotNull($callback, 'Callback should not be null. selectOne was likely not called.');

        if (\is_callable($callback)) {
            self::assertSame('Parent > Category Name', $callback($category));
        }

        $categoryNoBreadcrumb = new DemoEntity('cat-id', 'Alone');

        if (\is_callable($callback)) {
            self::assertSame('Alone', $callback($categoryNoBreadcrumb));
        }
    }

    public function testExecuteCreatesFooterRootWhenNoRootCategoriesExist(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn($this->createSearchResult([]));

        $capturedPayloads = [];
        $categoryRepository->expects(self::exactly(2))
            ->method('upsert')
            ->willReturnCallback(function (array $payload) use (&$capturedPayloads): EntityWrittenContainerEvent {
                $capturedPayloads[] = $payload;

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $selector = $this->createMock(EntityChoiceSelector::class);

        $command = new FooterCategoryCommand($categoryRepository, $selector);

        $input = new ArrayInput([]);
        $input->setInteractive(false);

        $status = $this->invoke($command, 'execute', [
            $input,
            new BufferedOutput(),
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertCount(2, $capturedPayloads);
        self::assertSame('Footer', $capturedPayloads[0][0]['name']);
        self::assertSame('Footer', $capturedPayloads[1][0]['name']);
        self::assertCount(3, $capturedPayloads[1][0]['children']);
    }

    public function testExecuteAsksUserBeforeShowingCategoryOptions(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $categoryRepository->method('search')->willReturn(
            $this->createSearchResult([new DemoEntity('root-id', 'Existing Category')])
        );

        $capturedPayloads = [];
        $categoryRepository->expects(self::exactly(2))
            ->method('upsert')
            ->willReturnCallback(function (array $payload) use (&$capturedPayloads): EntityWrittenContainerEvent {
                $capturedPayloads[] = $payload;

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->expects(self::never())->method('selectOne');

        $command = new FooterCategoryCommand($categoryRepository, $selector);

        $input = new ArrayInput([]);
        $input->setInteractive(false);

        $status = $this->invoke($command, 'execute', [
            $input,
            new BufferedOutput(),
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertCount(2, $capturedPayloads);
        self::assertSame('Footer', $capturedPayloads[0][0]['name']);
    }

    public function testExecuteUsesSelectedRootCategoryWhenUserConfirms(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $searchResult = $this->createSearchResult([new DemoEntity('root-id', 'My Footer')]);
        $categoryRepository->method('search')->willReturn($searchResult);

        $capturedPayload = null;
        $categoryRepository->expects(self::once())
            ->method('upsert')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload): EntityWrittenContainerEvent {
                $capturedPayload = $payload;

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')->willReturn([
            'id' => 'root-id',
            'name' => 'My Footer',
        ]);

        $command = new FooterCategoryCommand($categoryRepository, $selector);

        $input = new ArrayInput(['--no-interaction' => false]);
        $input->setStream(fopen('php://memory', 'r+', false));
        fwrite($input->getStream(), "y\n");
        rewind($input->getStream());

        $output = new BufferedOutput();

        $status = $this->invoke($command, 'execute', [
            $input,
            $output,
        ]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertIsArray($capturedPayload);
        self::assertSame('root-id', $capturedPayload[0]['id']);
        self::assertSame('My Footer', $capturedPayload[0]['name']);
        self::assertCount(3, $capturedPayload[0]['children']);
    }

    public function testCreateFooterRootCategoryUpsertsCorrectData(): void
    {
        $categoryRepository = $this->createMock(EntityRepository::class);
        $command = new FooterCategoryCommand(
            $categoryRepository,
            $this->createMock(EntityChoiceSelector::class)
        );

        $context = Context::createDefaultContext();

        $capturedPayload = null;
        $categoryRepository->expects(self::once())
            ->method('upsert')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload): EntityWrittenContainerEvent {
                $capturedPayload = $payload;

                return $this->createMock(EntityWrittenContainerEvent::class);
            });

        $result = $this->invoke($command, 'createFooterRootCategory', [$context]);

        self::assertSame(Uuid::fromStringToHex('Footer'), $result['id']);
        self::assertSame('Footer', $result['name']);
        self::assertTrue($result['active']);
        self::assertTrue($result['visible']);

        self::assertCount(1, $capturedPayload);
        self::assertSame($result['id'], $capturedPayload[0]['id']);
        self::assertSame($result['name'], $capturedPayload[0]['name']);
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
