<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductPayloadBuilder
{
    private const PRODUCT_NAME_SEEDS = [
        'Fashion & Apparel' => [
            'Slim Fit Cotton Shirt',
            'Classic Denim Jacket',
            'High-Waist Chino Trousers',
            'Breathable Mesh Sneakers',
            'Leather Strap Wristwatch',
            'Minimalist Crossbody Bag',
        ],
        'Phones & Electronics' => [
            '5G Dual SIM Smartphone',
            'Wireless Noise-Cancelling Headphones',
            'Ultra HD Smart TV',
            'Portable Bluetooth Speaker',
            'Gaming Laptop',
            'Fast-Charge Power Bank',
        ],
        'Home, Kitchen & Living' => [
            'Digital Air Fryer',
            'Compact Blender',
            'Orthopedic Mattress',
            'Modern Dining Chair Set',
            'Energy Saving Standing Fan',
            'Non-Stick Cookware Set',
        ],
        'Supermarket' => [
            'Premium Long Grain Rice',
            'Natural Spring Water',
            'Fortified Wheat Flour',
            'Cold Pressed Cooking Oil',
            'Multi-Surface Cleaner',
            'Family Pack Tissue Roll',
        ],
        'Beauty & Personal Care' => [
            'Hydrating Face Moisturizer',
            'Vitamin C Brightening Serum',
            'Long-Lasting Matte Lipstick',
            'Nourishing Hair Conditioner',
            'Fresh Citrus Eau de Parfum',
            'SPF 50 Daily Sunscreen',
        ],
        'Baby, Kids & Toys' => [
            'Ultra Soft Baby Diapers',
            'Organic Baby Cereal',
            'Educational Building Blocks',
            'Plush Animal Toy Set',
            'Toddler Learning Tablet',
            'Maternity Support Pillow',
        ],
        'Automotive' => [
            'All-Weather Car Floor Mat',
            'Heavy-Duty Car Battery',
            'High-Performance Engine Oil',
            'Universal Phone Car Mount',
            'LED Headlight Bulb Pair',
            'Premium Tire Care Kit',
        ],
        'Tools & Hardware' => [
            'Cordless Impact Drill',
            'Industrial Tool Box Set',
            'Adjustable Pipe Wrench',
            'Heavy-Duty Extension Cable',
            'Precision Screwdriver Kit',
            'High Torque Angle Grinder',
        ],
        'Sports & Outdoors' => [
            'Adjustable Dumbbell Set',
            'Professional Yoga Mat',
            'Waterproof Camping Tent',
            'All-Terrain Hiking Backpack',
            'Breathable Sports Jersey',
            'Insulated Outdoor Water Bottle',
        ],
        'Office & Business' => [
            'Ergonomic Office Chair',
            'A4 Premium Copy Paper',
            'Wireless All-in-One Printer',
            'Executive Desk Organizer',
            'Industrial Label Printer',
            'Heavy-Duty Packaging Tape',
        ],
        'Health & Wellness' => [
            'Multivitamin Daily Capsules',
            'Digital Blood Pressure Monitor',
            'Immunity Support Supplement',
            'Orthopedic Back Support Belt',
            'Infrared Forehead Thermometer',
            'Medical First Aid Kit',
        ],
    ];

    private const LOREM_IPSUM = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
    private const PRICE_MIN = 2;
    private const PRICE_MAX = 20;
    private const STOCK_MIN = 10000;
    private const STOCK_MAX = 20000;
    private const PROPERTIES_MIN = 3;
    private const PROPERTIES_MAX = 6;
    private const IMAGES_PER_PRODUCT = 5;
    private const VARIANT_PARENT_INTERVAL = 3;

    public function __construct(
        private readonly DeterministicValueGenerator $deterministicValueGenerator
    ) {
    }

    /**
     * @param array<int, array<int, string>> $groupedPropertyOptionIds
     * @param array<int, string> $salesChannelIds
     * @param array<int, string> $selectedMediaIds
     * @param array<int, string> $tagIds
     *
     * @return array<string, mixed>
     */
    public function build(
        string $categoryId,
        string $categoryName,
        ?string $parentCategoryId,
        string $manufacturerId,
        string $taxId,
        array $groupedPropertyOptionIds,
        array $salesChannelIds,
        array $selectedMediaIds,
        array $tagIds,
        int $index
    ): array {
        $productKey = \sprintf('product|%s|%d', $categoryId, $index);
        $productId = Uuid::fromStringToHex($productKey);
        $gross = (float)$this->deterministicInt($productKey . '|price', self::PRICE_MIN, self::PRICE_MAX);
        $net = round($gross / 1.075, 2);
        $name = $this->generateProductName($categoryName, $index, $productKey);

        $categoryIds = [$categoryId];

        if ($parentCategoryId !== null && $parentCategoryId !== $categoryId) {
            $categoryIds[] = $parentCategoryId;
        }

        $payload = [
            'id' => $productId,
            'name' => $name,
            'description' => self::LOREM_IPSUM,
            'productNumber' => $this->generateProductNumber($categoryName, $categoryId, $index),
            'stock' => $this->deterministicInt($productKey . '|stock', self::STOCK_MIN, self::STOCK_MAX),
            'active' => true,
            'manufacturerId' => $manufacturerId,
            'taxId' => $taxId,
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => $gross,
                'net' => $net,
                'linked' => false,
            ]],
            'categories' => array_map(
                static fn (string $id): array => ['id' => $id],
                array_values(array_unique($categoryIds))
            ),
        ];

        $propertyIds = $this->pickDeterministicPropertyIds($groupedPropertyOptionIds, $productKey);
        $payload['properties'] = array_map(
            static fn (string $id): array => ['id' => $id],
            $propertyIds
        );

        $payload['tags'] = array_map(
            static fn (string $id): array => ['id' => $id],
            $this->pickDeterministicTagIds($tagIds, $productKey)
        );

        $payload += $this->buildDimensionsAndTopSellerPayload($productKey);

        if ($salesChannelIds !== []) {
            $payload['productReviews'] = $this->buildProductReviews($productId, $productKey, $name, $salesChannelIds);
        }

        if ($this->isVariantParent($index)) {
            $payload += $this->buildVariantPayload(
                $productKey,
                $payload['productNumber'],
                $name,
                $groupedPropertyOptionIds,
                $salesChannelIds,
                $tagIds
            );
        }

        if ($selectedMediaIds !== []) {
            ['media' => $media, 'coverId' => $coverId] = $this->pickProductImages($productKey, $selectedMediaIds);
            $payload['media'] = $media;
            $payload['coverId'] = $coverId;
        }

        if ($salesChannelIds !== []) {
            $payload['visibilities'] = $this->buildVisibilities($productId, $salesChannelIds);
        }

        return $payload;
    }

    /**
     * @param array<int, string> $salesChannelIds
     *
     * @return array<int, array{id: string, salesChannelId: string, visibility: int}>
     */
    private function buildVisibilities(string $productId, array $salesChannelIds): array
    {
        $visibilities = [];

        foreach ($salesChannelIds as $salesChannelId) {
            $visibilities[] = [
                'id' => Uuid::fromStringToHex($productId . '|' . $salesChannelId),
                'salesChannelId' => $salesChannelId,
                'visibility' => 30,
            ];
        }

        return $visibilities;
    }

    /**
     * Builds one deterministic variant child per option for every third parent product.
     *
     * @param array<int, array<int, string>> $groupedPropertyOptionIds
     * @param array<int, string> $salesChannelIds
     * @param array<int, string> $tagIds
     *
     * @return array<string, mixed>
     */
    private function buildVariantPayload(
        string $productKey,
        string $parentProductNumber,
        string $parentName,
        array $groupedPropertyOptionIds,
        array $salesChannelIds,
        array $tagIds
    ): array {
        $variantOptionIds = $this->pickVariantOptionIds($groupedPropertyOptionIds, $productKey);

        if ($variantOptionIds === []) {
            return []; // @codeCoverageIgnore
        }

        $children = [];

        foreach ($variantOptionIds as $position => $optionId) {
            $childProductKey = $productKey . '|variant|' . $position;
            $childProductId = Uuid::fromStringToHex($childProductKey);
            $childProductNumber = $parentProductNumber . '.' . ($position + 1);

            $children[] = [
                'id' => $childProductId,
                'productNumber' => $childProductNumber,
                'name' => $parentName . ' Variant ' . ($position + 1),
                'stock' => $this->deterministicInt(
                    $productKey . '|variant-stock|' . $position,
                    self::STOCK_MIN,
                    self::STOCK_MAX
                ),
                ...$this->buildDimensionsAndTopSellerPayload($childProductKey),
                'options' => [
                    ['id' => $optionId],
                ],
                'tags' => array_map(
                    static fn (string $id): array => ['id' => $id],
                    $this->pickDeterministicTagIds($tagIds, $childProductKey)
                ),
                'productReviews' => $salesChannelIds !== []
                    ? $this->buildProductReviews($childProductId, $childProductKey, $parentName . ' Variant ' . ($position + 1), $salesChannelIds)
                    : [],
            ];
        }

        return [
            'configuratorSettings' => array_map(
                static fn (string $optionId): array => ['optionId' => $optionId],
                $variantOptionIds
            ),
            'children' => $children,
        ];
    }

    /**
     * Picks one property group and uses all of its options as variant axes.
     *
     * @param array<int, array<int, string>> $groupedPropertyOptionIds
     *
     * @return array<int, string>
     */
    private function pickVariantOptionIds(array $groupedPropertyOptionIds, string $productKey): array
    {
        $candidateGroups = array_values(array_filter(
            $groupedPropertyOptionIds,
            static fn (array $optionIds): bool => count($optionIds) > 1
        ));

        if ($candidateGroups === []) {
            return [];
        }

        $selectedGroupIndex = $this->deterministicInt(
            $productKey . '|variant-group',
            0,
            count($candidateGroups) - 1
        );

        return array_values(array_unique($candidateGroups[$selectedGroupIndex]));
    }

    /**
     * Builds deterministic product review entries.
     *
     * @param array<int, string> $salesChannelIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProductReviews(string $productId, string $productKey, string $productName, array $salesChannelIds): array
    {
        $reviewCount = $this->deterministicInt($productKey . '|review-count', 4, 10);
        $reviews = [];

        for ($i = 1; $i <= $reviewCount; ++$i) {
            $reviewKey = $productKey . '|review|' . $i;
            $salesChannelId = $salesChannelIds[$this->deterministicInt(
                $reviewKey . '|sales-channel',
                0,
                count($salesChannelIds) - 1
            )];

            $reviews[] = [
                'id' => Uuid::fromStringToHex($reviewKey),
                'productId' => $productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'externalUser' => $this->generateReviewerName($reviewKey),
                'externalEmail' => strtolower(str_replace(' ', '.', $this->generateReviewerName($reviewKey))) . '@example.com',
                'title' => $productName . ' review ' . $i,
                'content' => self::LOREM_IPSUM,
                'points' => (float)$this->deterministicInt($reviewKey . '|points', 1, 5),
                'status' => true,
                'comment' => self::LOREM_IPSUM . ' ' . self::LOREM_IPSUM,
            ];
        }

        return $reviews;
    }

    private function generateReviewerName(string $key): string
    {
        $firstNames = ['Amina', 'David', 'Fatima', 'John', 'Chinwe', 'Sarah', 'Ibrahim', 'Grace'];
        $lastNames = ['Okafor', 'Smith', 'Johnson', 'Nwosu', 'Brown', 'Taylor', 'Adeyemi', 'Williams'];

        $firstName = $firstNames[$this->deterministicInt($key . '|first-name', 0, count($firstNames) - 1)];
        $lastName = $lastNames[$this->deterministicInt($key . '|last-name', 0, count($lastNames) - 1)];

        return $firstName . ' ' . $lastName;
    }

    /**
     * Picks 2-5 deterministic tag IDs for a product.
     *
     * @param array<int, string> $tagIds
     *
     * @return array<int, string>
     */
    private function pickDeterministicTagIds(array $tagIds, string $productKey): array
    {
        if ($tagIds === []) {
            return []; // @codeCoverageIgnore
        }

        $pickCount = min(
            $this->deterministicInt($productKey . '|tag-count', 2, 5),
            count($tagIds)
        );

        $weightedIndexes = [];

        foreach (array_keys($tagIds) as $tagIndex) {
            $weightedIndexes[$tagIndex] = $this->deterministicInt(
                $productKey . '|tag-weight|' . $tagIndex,
                0,
                1000000
            );
        }

        asort($weightedIndexes);
        $selectedIndexes = array_slice(array_keys($weightedIndexes), 0, $pickCount);

        $selectedTagIds = [];

        foreach ($selectedIndexes as $tagIndex) {
            $selectedTagIds[] = $tagIds[$tagIndex];
        }

        return array_values(array_unique($selectedTagIds));
    }

    /**
     * Builds realistic weight and dimensions plus a deterministic topseller flag.
     *
     * @return array<string, mixed>
     */
    private function buildDimensionsAndTopSellerPayload(string $productKey): array
    {
        return [
            'weight' => $this->deterministicFloat($productKey . '|weight', 0.25, 12.00, 2),
            'width' => $this->deterministicFloat($productKey . '|width', 10.0, 120.0, 1),
            'height' => $this->deterministicFloat($productKey . '|height', 2.0, 80.0, 1),
            'length' => $this->deterministicFloat($productKey . '|length', 10.0, 150.0, 1),
            'markAsTopseller' => $this->deterministicInt($productKey . '|topseller', 1, 10) === 1,
        ];
    }

    private function generateProductNumber(string $categoryName, string $categoryId, int $index): string
    {
        $categoryToken = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $categoryName));
        $categoryToken = substr($categoryToken, 0, 6) ?: 'GEN';

        return \sprintf(
            'KHSW-%s-%s-%03d',
            $categoryToken,
            strtoupper(substr($categoryId, 0, 6)),
            $index
        );
    }

    private function generateProductName(string $categoryName, int $index, string $productKey): string
    {
        $seedNames = self::PRODUCT_NAME_SEEDS[$categoryName] ?? ['Premium Product'];
        $seedIndex = $this->deterministicInt($productKey . '|name-seed', 0, count($seedNames) - 1);
        $seed = $seedNames[$seedIndex];

        return \sprintf('%s %d', $seed, $index);
    }

    /**
     * @param array<int, array<int, string>> $groupedPropertyOptionIds
     *
     * @return array<int, string>
     */
    private function pickDeterministicPropertyIds(array $groupedPropertyOptionIds, string $productKey): array
    {
        $groupCount = count($groupedPropertyOptionIds);

        if ($groupCount === 0) {
            return []; // @codeCoverageIgnore
        }

        $pickCount = min(
            $this->deterministicInt($productKey . '|properties-count', self::PROPERTIES_MIN, self::PROPERTIES_MAX),
            $groupCount
        );

        $weightedIndexes = [];

        foreach (array_keys($groupedPropertyOptionIds) as $groupIndex) {
            $weightedIndexes[$groupIndex] = $this->deterministicInt(
                $productKey . '|group-index|' . $groupIndex,
                0,
                1000000
            );
        }
        asort($weightedIndexes);
        $selectedIndexes = array_slice(array_keys($weightedIndexes), 0, $pickCount);

        $propertyIds = [];

        foreach ($selectedIndexes as $groupIndex) {
            $optionIds = $groupedPropertyOptionIds[$groupIndex];
            $optionIndex = $this->deterministicInt(
                $productKey . '|option-index|' . $groupIndex,
                0,
                count($optionIds) - 1
            );
            $propertyIds[] = $optionIds[$optionIndex];
        }

        return array_values(array_unique($propertyIds));
    }

    /**
     * Picks 5 deterministic images and selects one as the cover image.
     *
     * @param array<int, string> $selectedMediaIds
     *
     * @return array{media: array<int, array{id: string, mediaId: string}>, coverId: ?string}
     */
    private function pickProductImages(string $productKey, array $selectedMediaIds): array
    {
        $mediaCount = \count($selectedMediaIds);
        $pickCount = min(self::IMAGES_PER_PRODUCT, $mediaCount);

        if ($pickCount === 0) {
            return ['media' => [], 'coverId' => null];
        }

        $pickedIndexes = [];
        for ($i = 0; $i < $pickCount; ++$i) {
            $weight = $this->deterministicInt(
                $productKey . '|media-index|' . $i,
                0,
                1000000
            );
            $pickedIndexes[] = $this->deterministicInt(
                $productKey . '|media-weight|' . $weight,
                0,
                $mediaCount - 1
            );
        }

        $pickedIndexes = array_values(array_unique($pickedIndexes));
        $mediaIds = array_map(
            static fn (int $index): string => $selectedMediaIds[$index],
            $pickedIndexes
        );

        $media = array_map(
            static fn (string $mediaId, int $position): array => [
                'id' => Uuid::fromStringToHex($productKey . '|product-media|' . $position . '|' . $mediaId),
                'mediaId' => $mediaId,
            ],
            $mediaIds,
            array_keys($mediaIds)
        );

        $coverIndex = $this->deterministicInt(
            $productKey . '|cover-index',
            0,
            \count($media) - 1
        );
        $coverId = $media[$coverIndex]['id'];

        return ['media' => $media, 'coverId' => $coverId];
    }

    private function deterministicInt(string $key, int $min, int $max): int
    {
        return $this->deterministicValueGenerator->int($key, $min, $max);
    }

    private function deterministicFloat(string $key, float $min, float $max, int $precision): float
    {
        return $this->deterministicValueGenerator->float($key, $min, $max, $precision);
    }

    private function isVariantParent(int $index): bool
    {
        return $index % self::VARIANT_PARENT_INTERVAL === 0;
    }
}
