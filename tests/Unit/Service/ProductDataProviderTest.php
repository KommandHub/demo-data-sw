<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Service;

use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;
use Kommandhub\DemoDataSW\Service\ProductDataProvider;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntity;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ProductDataProviderTest extends ReflectionTestCase
{
    public function testAskManufacturerReturnsNullWhenNoneFound(): void
    {
        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')->willReturn(null);

        $provider = $this->createProvider(['selector' => $selector]);
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(self::once())->method('error');

        $result = $provider->askManufacturer($io, Context::createDefaultContext());
        self::assertNull($result);
    }

    public function testAskManufacturerReturnsSelection(): void
    {
        $selection = ['id' => 'm1', 'name' => 'Manufacturer'];
        $selector = $this->createMock(EntityChoiceSelector::class);
        $selector->method('selectOne')->willReturnCallback(function ($io, $repo, $context, $criteria, $prompt, $callback) use ($selection) {
            $manufacturer = $this->createMock(ProductManufacturerEntity::class);
            $manufacturer->method('getName')->willReturn('Manufacturer');
            $callback($manufacturer); // Execute callback for coverage

            return $selection;
        });

        $provider = $this->createProvider(['selector' => $selector]);
        $io = $this->createMock(SymfonyStyle::class);

        $result = $provider->askManufacturer($io, Context::createDefaultContext());
        self::assertSame($selection, $result);
    }

    public function testFetchTargetCategories(): void
    {
        $category1 = $this->createMock(CategoryEntity::class);
        $category1->method('getId')->willReturn('c1');
        $category1->method('getUniqueIdentifier')->willReturn('c1');
        $category1->method('getName')->willReturn('Fashion & Apparel');
        $category1->method('getParentId')->willReturn('p1');

        $category2 = $this->createMock(CategoryEntity::class);
        $category2->method('getId')->willReturn('c2');
        $category2->method('getUniqueIdentifier')->willReturn('c2');
        $category2->method('getName')->willReturn(null);
        $category2->method('getParentId')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([$category1, $category2]));

        $provider = $this->createProvider(['categoryRepository' => $repo]);
        $result = $provider->fetchTargetCategories(Context::createDefaultContext());

        self::assertCount(2, $result);
        self::assertSame('c1', $result[0]['id']);
        self::assertSame('Fashion & Apparel', $result[0]['name']);
        self::assertSame('General', $result[1]['name']);
    }

    public function testFetchGroupedPropertyOptionIds(): void
    {
        $option = $this->createMock(PropertyGroupOptionEntity::class);
        $option->method('getId')->willReturn('o1');

        $group1 = $this->createMock(PropertyGroupEntity::class);
        $group1->method('getUniqueIdentifier')->willReturn('g1');
        $group1->method('getOptions')->willReturn(new PropertyGroupOptionCollection([$option]));

        $group2 = $this->createMock(PropertyGroupEntity::class);
        $group2->method('getUniqueIdentifier')->willReturn('g2');
        $group2->method('getOptions')->willReturn(new PropertyGroupOptionCollection([]));

        $group3 = $this->createMock(PropertyGroupEntity::class);
        $group3->method('getUniqueIdentifier')->willReturn('g3');
        $group3->method('getOptions')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([$group1, $group2, $group3]));

        $provider = $this->createProvider(['propertyGroupRepository' => $repo]);
        $result = $provider->fetchGroupedPropertyOptionIds(Context::createDefaultContext());

        self::assertCount(1, $result);
        self::assertSame(['o1'], $result[0]);
    }

    public function testFetchTaxId(): void
    {
        $tax = new DemoEntity('t1', 'Tax');
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([$tax]));

        $provider = $this->createProvider(['taxRepository' => $repo]);
        $result = $provider->fetchTaxId(Context::createDefaultContext());

        self::assertSame('t1', $result);
    }

    public function testFetchTaxIdReturnsNullWhenNoneFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([]));

        $provider = $this->createProvider(['taxRepository' => $repo]);
        $result = $provider->fetchTaxId(Context::createDefaultContext());

        self::assertNull($result);
    }

    public function testFetchSalesChannelIds(): void
    {
        $sc = new DemoEntity('s1', 'SC');
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([$sc]));

        $provider = $this->createProvider(['salesChannelRepository' => $repo]);
        $result = $provider->fetchSalesChannelIds(Context::createDefaultContext());

        self::assertSame(['s1'], $result);
    }

    public function testFetchAvailableMediaReturnsEmptyOnException(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willThrowException(new \Exception());

        $provider = $this->createProvider(['mediaRepository' => $repo]);
        $result = $provider->fetchAvailableMedia(Context::createDefaultContext());

        self::assertEmpty($result);
    }

    public function testFetchAvailableMediaSuccess(): void
    {
        $media1 = $this->createMock(MediaEntity::class);
        $media1->method('getId')->willReturn('media-1');
        $media1->method('getUniqueIdentifier')->willReturn('media-1');
        $media1->method('getFileName')->willReturn('demo-product-1');

        $media2 = $this->createMock(MediaEntity::class);
        $media2->method('getId')->willReturn('media-2');
        $media2->method('getUniqueIdentifier')->willReturn('media-2');
        $media2->method('getFileName')->willReturn(null);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->createSearchResult([$media1, $media2]));

        $provider = $this->createProvider(['mediaRepository' => $repo]);
        $result = $provider->fetchAvailableMedia(Context::createDefaultContext());

        self::assertCount(2, $result);
        self::assertSame('media-1', $result[0]['id']);
        self::assertSame('demo-product-1', $result[0]['name']);
        self::assertSame('media-2', $result[1]['id']);
        self::assertSame('Unnamed Media', $result[1]['name']);
    }

    public function testAskSelectImages(): void
    {
        $provider = $this->createProvider();
        $media = [
            ['id' => 'm1', 'name' => 'Image 1'],
            ['id' => 'm2', 'name' => 'Image 2'],
        ];

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(self::exactly(3))
            ->method('askQuestion')
            ->willReturnOnConsecutiveCalls(
                'Image 1 (m1)',
                'Image 1 (m1)', // Duplicate selection
                'Done selecting images'
            );

        $selected = $provider->askSelectImages($io, $media);

        self::assertSame(['m1'], $selected);
    }

    public function testAskSelectImagesReturnsEmptyWhenNoMedia(): void
    {
        $provider = $this->createProvider();
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(self::once())->method('warning')->with('No media available in system.');

        $selected = $provider->askSelectImages($io, []);
        self::assertEmpty($selected);
    }

    public function testAskSelectImagesReturnsEmptyWhenStoppedEarly(): void
    {
        $provider = $this->createProvider();
        $media = [['id' => 'm1', 'name' => 'Image 1']];
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('askQuestion')->willReturn('Done selecting images');
        $io->expects(self::once())->method('warning')->with('No images selected.');

        $selected = $provider->askSelectImages($io, $media);
        self::assertEmpty($selected);
    }

    public function testSeedTags(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(self::once())->method('upsert');

        $provider = $this->createProvider(['tagRepository' => $repo]);
        $tagIds = $provider->seedTags(Context::createDefaultContext());

        self::assertNotEmpty($tagIds);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createProvider(array $overrides = []): ProductDataProvider
    {
        return new ProductDataProvider(
            $overrides['categoryRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['propertyGroupRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['taxRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['salesChannelRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['mediaRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['productManufacturerRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['tagRepository'] ?? $this->createMock(EntityRepository::class),
            $overrides['selector'] ?? new EntityChoiceSelector()
        );
    }

    /**
     * @param array<int, mixed> $entities
     */
    private function createSearchResult(array $entities): EntitySearchResult
    {
        $collection = new EntityCollection($entities);

        return new EntitySearchResult(
            'demo',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
