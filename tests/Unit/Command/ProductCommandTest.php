<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Command;

use Kommandhub\DemoDataSW\Command\ProductCommand;
use Kommandhub\DemoDataSW\Service\DeterministicValueGenerator;
use Kommandhub\DemoDataSW\Service\ProductDataProvider;
use Kommandhub\DemoDataSW\Service\ProductPayloadBuilder;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class ProductCommandTest extends ReflectionTestCase
{
    public function testExecuteReturnsFailureWhenNoManufacturerSelected(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(null);

        $command = $this->createCommand(['productDataProvider' => $provider]);

        $status = $command->run(new ArrayInput([]), new BufferedOutput());

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenNoCategoriesFound(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(['id' => 'm1', 'name' => 'Man']);
        $provider->method('fetchTargetCategories')->willReturn([]);

        $command = $this->createCommand(['productDataProvider' => $provider]);

        $status = $command->run(new ArrayInput([]), new BufferedOutput());

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenNoPropertyOptionsFound(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(['id' => 'm1', 'name' => 'Man']);
        $provider->method('fetchTargetCategories')->willReturn([['id' => 'c1', 'name' => 'Cat', 'parentId' => null]]);
        $provider->method('fetchGroupedPropertyOptionIds')->willReturn([]);

        $command = $this->createCommand(['productDataProvider' => $provider]);

        $status = $command->run(new ArrayInput([]), new BufferedOutput());

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteReturnsFailureWhenNoTaxFound(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(['id' => 'm1', 'name' => 'Man']);
        $provider->method('fetchTargetCategories')->willReturn([['id' => 'c1', 'name' => 'Cat', 'parentId' => null]]);
        $provider->method('fetchGroupedPropertyOptionIds')->willReturn([['o1']]);
        $provider->method('fetchTaxId')->willReturn(null);

        $command = $this->createCommand(['productDataProvider' => $provider]);

        $status = $command->run(new ArrayInput([]), new BufferedOutput());

        self::assertSame(Command::FAILURE, $status);
    }

    public function testExecuteSuccess(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(['id' => 'm1', 'name' => 'Man']);
        $provider->method('fetchTargetCategories')->willReturn([['id' => 'c1', 'name' => 'Cat', 'parentId' => null]]);
        $provider->method('fetchGroupedPropertyOptionIds')->willReturn([['o1']]);
        $provider->method('fetchTaxId')->willReturn('t1');
        $provider->method('fetchSalesChannelIds')->willReturn(['s1']);
        $provider->method('fetchAvailableMedia')->willReturn([['id' => 'media1', 'name' => 'Media']]);
        $provider->method('askSelectImages')->willReturn(['media1']);
        $provider->method('seedTags')->willReturn(['tag1']);

        $builder = $this->createMock(ProductPayloadBuilder::class);
        $builder->method('build')->willReturn(['id' => 'p1']);

        $productRepo = $this->createMock(EntityRepository::class);
        $productRepo->expects(self::once())->method('upsert');

        $command = $this->createCommand([
            'productDataProvider' => $provider,
            'productPayloadBuilder' => $builder,
            'productRepository' => $productRepo,
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testExecuteSuccessWithoutImages(): void
    {
        $provider = $this->createMock(ProductDataProvider::class);
        $provider->method('askManufacturer')->willReturn(['id' => 'm1', 'name' => 'Man']);
        $provider->method('fetchTargetCategories')->willReturn([['id' => 'c1', 'name' => 'Cat', 'parentId' => null]]);
        $provider->method('fetchGroupedPropertyOptionIds')->willReturn([['o1']]);
        $provider->method('fetchTaxId')->willReturn('t1');
        $provider->method('fetchSalesChannelIds')->willReturn(['s1']);
        $provider->method('fetchAvailableMedia')->willReturn([]);
        $provider->method('askSelectImages')->willReturn([]);
        $provider->method('seedTags')->willReturn(['tag1']);

        $builder = $this->createMock(ProductPayloadBuilder::class);
        $builder->method('build')->willReturn(['id' => 'p1']);

        $productRepo = $this->createMock(EntityRepository::class);
        $productRepo->expects(self::once())->method('upsert');

        $command = $this->createCommand([
            'productDataProvider' => $provider,
            'productPayloadBuilder' => $builder,
            'productRepository' => $productRepo,
        ]);

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No images selected', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createCommand(array $overrides = []): ProductCommand
    {
        return new ProductCommand(
            $overrides['productRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['productDataProvider'] ?? $this->createMock(ProductDataProvider::class),
            $overrides['productPayloadBuilder'] ?? $this->createMock(ProductPayloadBuilder::class),
            new DeterministicValueGenerator()
        );
    }
}
