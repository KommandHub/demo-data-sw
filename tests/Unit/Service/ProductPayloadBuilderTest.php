<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Service;

use Kommandhub\DemoDataSW\Service\DeterministicValueGenerator;
use Kommandhub\DemoDataSW\Service\ProductPayloadBuilder;
use Kommandhub\DemoDataSW\Tests\Unit\Support\ReflectionTestCase;
use Shopware\Core\Defaults;

final class ProductPayloadBuilderTest extends ReflectionTestCase
{
    public function testBuildProductPayloadIncludesVariantsMediaTagsAndReviews(): void
    {
        $builder = $this->createBuilder();

        $payload = $builder->build(
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'Fashion & Apparel',
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'cccccccccccccccccccccccccccccccc',
            'dddddddddddddddddddddddddddddddd',
            [['opt-1', 'opt-2', 'opt-3']],
            ['eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'],
            [
                'media-1',
                'media-2',
                'media-3',
                'media-4',
                'media-5',
                'media-6',
            ],
            ['tag-1', 'tag-2', 'tag-3', 'tag-4', 'tag-5'],
            3
        );

        self::assertCount(2, $payload['categories']);
        self::assertContains('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', array_column($payload['categories'], 'id'));
        self::assertContains('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', array_column($payload['categories'], 'id'));
        self::assertSame(3, count($payload['configuratorSettings'] ?? []));
        self::assertCount(3, $payload['children'] ?? []);

        self::assertGreaterThanOrEqual(10000, $payload['stock']);
        self::assertLessThanOrEqual(20000, $payload['stock']);
        self::assertArrayHasKey('weight', $payload);
        self::assertArrayHasKey('width', $payload);
        self::assertArrayHasKey('height', $payload);
        self::assertArrayHasKey('length', $payload);
        self::assertIsBool($payload['markAsTopseller']);

        self::assertSame(Defaults::CURRENCY, $payload['price'][0]['currencyId']);
        self::assertGreaterThanOrEqual(2, $payload['price'][0]['gross']);
        self::assertLessThanOrEqual(20, $payload['price'][0]['gross']);

        self::assertGreaterThanOrEqual(2, count($payload['tags']));
        self::assertLessThanOrEqual(5, count($payload['tags']));

        foreach ($payload['tags'] as $tag) {
            self::assertArrayHasKey('id', $tag);
        }

        self::assertNotEmpty($payload['media']);
        $mediaIds = array_column($payload['media'], 'id');
        self::assertContains($payload['coverId'], $mediaIds);

        self::assertGreaterThanOrEqual(4, count($payload['productReviews']));
        self::assertLessThanOrEqual(10, count($payload['productReviews']));
        self::assertSame(Defaults::LIVE_VERSION, $payload['productReviews'][0]['productVersionId']);
        self::assertTrue($payload['productReviews'][0]['status']);

        foreach ($payload['children'] as $child) {
            self::assertArrayHasKey('id', $child);
            self::assertArrayHasKey('tags', $child);
            self::assertArrayHasKey('productReviews', $child);
            self::assertMatchesRegularExpression('/\\.\\d+$/', $child['productNumber']);
            self::assertGreaterThanOrEqual(10000, $child['stock']);
            self::assertLessThanOrEqual(20000, $child['stock']);
        }
    }

    public function testGenerateProductNameAndNumber(): void
    {
        $builder = $this->createBuilder();
        $name = $this->invoke($builder, 'generateProductName', ['Fashion & Apparel', 1, 'p1']);
        $number = $this->invoke($builder, 'generateProductNumber', ['Fashion & Apparel', 'cat1', 1]);

        self::assertStringContainsString('Shirt', $name);
        self::assertStringContainsString('FASHIO', $number);
    }

    public function testIsVariantParent(): void
    {
        $builder = $this->createBuilder();
        self::assertTrue($this->invoke($builder, 'isVariantParent', [3]));
        self::assertFalse($this->invoke($builder, 'isVariantParent', [1]));
    }

    public function testPickDeterministicPropertyIds(): void
    {
        $builder = $this->createBuilder();
        $groupedIds = [
            ['o1', 'o2'],
            ['o3', 'o4'],
        ];

        $picked = $this->invoke($builder, 'pickDeterministicPropertyIds', [$groupedIds, 'key']);
        self::assertNotEmpty($picked);

        foreach ($picked as $id) {
            self::assertContains($id, ['o1', 'o2', 'o3', 'o4']);
        }
    }

    public function testBuildProductPayloadWithEmptyOptionalFields(): void
    {
        $builder = $this->createBuilder();

        $payload = $builder->build(
            'cat1',
            'Fashion & Apparel',
            'cat1', // Same as categoryId
            'm1',
            't1',
            [['o1']], // Only one option, so no variants
            [], // Empty sales channels
            [], // Empty media
            [], // Empty tags
            1 // Not a variant parent (index 1)
        );

        self::assertCount(1, $payload['categories']);
        self::assertArrayNotHasKey('configuratorSettings', $payload);
        self::assertArrayNotHasKey('children', $payload);
        self::assertArrayNotHasKey('visibilities', $payload);
        self::assertArrayNotHasKey('productReviews', $payload);
        self::assertArrayNotHasKey('media', $payload);
        self::assertArrayNotHasKey('coverId', $payload);
        self::assertEmpty($payload['tags']);
    }

    public function testPickVariantOptionIdsReturnsEmptyWhenNoCandidateGroups(): void
    {
        $builder = $this->createBuilder();
        $groupedIds = [['o1']]; // Only one option per group

        $result = $this->invoke($builder, 'pickVariantOptionIds', [$groupedIds, 'key']);
        self::assertEmpty($result);
    }

    public function testDeterministicMethods(): void
    {
        $builder = $this->createBuilder();
        self::assertIsInt($this->invoke($builder, 'deterministicInt', ['key', 1, 10]));
        self::assertIsFloat($this->invoke($builder, 'deterministicFloat', ['key', 1.0, 10.0, 2]));
    }

    public function testBuildVisibilities(): void
    {
        $builder = $this->createBuilder();
        $result = $this->invoke($builder, 'buildVisibilities', ['p1', ['s1', 's2']]);
        self::assertCount(2, $result);
        self::assertSame('s1', $result[0]['salesChannelId']);
        self::assertSame(30, $result[0]['visibility']);
    }

    public function testBuildProductReviews(): void
    {
        $builder = $this->createBuilder();
        $result = $this->invoke($builder, 'buildProductReviews', ['p1', 'key', 'Product', ['s1']]);
        self::assertNotEmpty($result);
        self::assertSame('p1', $result[0]['productId']);
    }

    public function testBuildDimensionsAndTopSellerPayload(): void
    {
        $builder = $this->createBuilder();
        $result = $this->invoke($builder, 'buildDimensionsAndTopSellerPayload', ['key']);
        self::assertArrayHasKey('weight', $result);
        self::assertArrayHasKey('markAsTopseller', $result);
    }

    public function testGenerateReviewerName(): void
    {
        $builder = $this->createBuilder();
        $result = $this->invoke($builder, 'generateReviewerName', ['key']);
        self::assertIsString($result);
        self::assertStringContainsString(' ', $result);
    }

    public function testPickProductImagesReturnsEmptyWhenNoIdsProvided(): void
    {
        $builder = $this->createBuilder();
        $result = $this->invoke($builder, 'pickProductImages', ['key', []]);
        self::assertSame(['media' => [], 'coverId' => null], $result);
    }

    private function createBuilder(): ProductPayloadBuilder
    {
        return new ProductPayloadBuilder(new DeterministicValueGenerator());
    }
}
