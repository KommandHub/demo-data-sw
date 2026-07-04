<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProductDataProvider
{
    private const TARGET_CATEGORY_NAMES = [
        'Fashion & Apparel',
        "Men's Fashion",
        'Shirts & Polos',
        'Native Wear',
        'Jeans & Trousers',
        'Shoes & Sneakers',
        'Underwear',
        "Women's Fashion",
        'Dresses',
        'African Wear',
        'Shoes & Heels',
        'Handbags',
        'Jewelry',
        'Kids Fashion',
        'Boys',
        'Girls',
        'Baby Clothing',
        'School Wear',
        'Accessories',
        'Watches',
        'Belts',
        'Sunglasses',
        'Caps & Hats',
        'Phones & Electronics',
        'Mobile Phones',
        'Android Phones',
        'iPhones',
        'Feature Phones',
        'Phone Accessories',
        'Cases',
        'Chargers',
        'Power Banks',
        'Headphones',
        'Screen Protectors',
        'Computers',
        'Laptops',
        'Desktops',
        'Monitors',
        'Storage Devices',
        'Networking',
        'Routers',
        'Modems',
        'Cables',
        'Home, Kitchen & Living',
        'Kitchen',
        'Blenders',
        'Microwaves',
        'Air Fryers',
        'Cookers',
        'Home Appliances',
        'Fans',
        'Air Conditioners',
        'Generators',
        'Inverters & Solar',
        'Furniture',
        'Beds',
        'Sofas',
        'Tables',
        'Chairs',
        'Supermarket',
        'Food Staples',
        'Rice',
        'Beans',
        'Flour',
        'Cooking Oil',
        'Beverages',
        'Soft Drinks',
        'Water',
        'Juice',
        'Household Essentials',
        'Detergents',
        'Tissue & Paper',
        'Cleaning Supplies',
        'Beauty & Personal Care',
        'Skincare',
        'Hair Care',
        'Makeup',
        'Fragrances',
        'Baby, Kids & Toys',
        'Diapers & Wipes',
        'Baby Food',
        'Toys & Games',
        'Maternity',
        'Automotive',
        'Car Parts',
        'Tyres',
        'Batteries',
        'Car Accessories',
        'Tools & Hardware',
        'Power Tools',
        'Hand Tools',
        'Plumbing',
        'Electrical',
        'Sports & Outdoors',
        'Fitness Equipment',
        'Team Sports',
        'Camping & Outdoor',
        'Office & Business',
        'Stationery',
        'Printers & Ink',
        'Packaging Materials',
        'Office Furniture',
        'Health & Wellness',
        'Vitamins & Supplements',
        'Medical Supplies',
        'Personal Health',
    ];

    private const TARGET_PROPERTY_GROUP_NAMES = [
        'Color',
        'Clothing Size',
        'Shoe Size',
        'Material',
        'Storage',
        'RAM',
        'Screen Size',
        'Weight',
        'Volume',
        'Power',
        'Energy Rating',
        'Connectivity',
        'Skin Type',
        'Fragrance Family',
        'Vehicle Type',
        'Paper Size',
        'Country of Origin',
        'Warranty',
        'Condition',
        'Made In',
    ];

    private const TAG_SEEDS = [
        'New Arrival',
        'Best Seller',
        'Featured',
        'Trending',
        'Limited Edition',
        'Premium',
        'Budget Friendly',
        'Top Rated',
        'Fast Shipping',
        'Eco Friendly',
        'Handmade',
        'Local Favorite',
        'Imported',
        'Summer Collection',
        'Winter Collection',
        'Gift Idea',
        'Everyday Use',
        'Durable',
        'Popular',
        'Exclusive',
    ];

    private const SELECTED_IMAGES_COUNT = 10;

    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<PropertyGroupCollection> $propertyGroupRepository
     * @param EntityRepository<TaxCollection> $taxRepository
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<MediaCollection> $mediaRepository
     * @param EntityRepository<ProductManufacturerCollection> $productManufacturerRepository
     * @param EntityRepository<TagCollection> $tagRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $propertyGroupRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $productManufacturerRepository,
        private readonly EntityRepository $tagRepository,
        private readonly EntityChoiceSelector $entityChoiceSelector
    ) {
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function askManufacturer(SymfonyStyle $io, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $manufacturer = $this->entityChoiceSelector->selectOne(
            $io,
            $this->productManufacturerRepository,
            $context,
            $criteria,
            'Select manufacturer to use for generated products:',
            static function ($manufacturer): string {
                /** @var ProductManufacturerEntity $manufacturer */
                return $manufacturer->getName() ?? 'Unknown Manufacturer';
            }
        );

        if ($manufacturer === null) {
            $io->error('No manufacturers found. Please create a manufacturer first.');

            return null;
        }

        return $manufacturer;
    }

    /**
     * @return array<int, array{id: string, name: string, parentId: string|null}>
     */
    public function fetchTargetCategories(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', self::TARGET_CATEGORY_NAMES));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = [];

        foreach ($this->categoryRepository->search($criteria, $context)->getEntities() as $category) {
            /** @var CategoryEntity $category */
            $result[] = [
                'id' => $category->getId(),
                'name' => $category->getName() ?? 'General',
                'parentId' => $category->getParentId(),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function fetchGroupedPropertyOptionIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', self::TARGET_PROPERTY_GROUP_NAMES));
        $criteria->addAssociation('options');

        $groupedOptionIds = [];

        foreach ($this->propertyGroupRepository->search($criteria, $context)->getEntities() as $group) {
            /** @var PropertyGroupEntity $group */
            $optionIds = [];

            $options = $group->getOptions();

            if ($options !== null) {
                foreach ($options as $option) {
                    /** @var PropertyGroupOptionEntity $option */
                    $optionIds[] = $option->getId();
                }
            }

            if ($optionIds !== []) {
                $groupedOptionIds[] = $optionIds;
            }
        }

        return $groupedOptionIds;
    }

    public function fetchTaxId(Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addSorting(new FieldSorting('taxRate', FieldSorting::DESCENDING));

        $tax = $this->taxRepository->search($criteria, $context)->first();

        /** @var TaxEntity|null $tax */
        return $tax?->getId();
    }

    /**
     * @return array<int, string>
     */
    public function fetchSalesChannelIds(Context $context): array
    {
        $criteria = new Criteria();

        $salesChannelIds = [];

        foreach ($this->salesChannelRepository->search($criteria, $context)->getEntities() as $salesChannel) {
            /** @var SalesChannelEntity $salesChannel */
            $salesChannelIds[] = $salesChannel->getId();
        }

        /** @var array<int, string> $salesChannelIds */
        $uniqueIds = array_unique($salesChannelIds);

        return array_values($uniqueIds);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function fetchAvailableMedia(Context $context): array
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new ContainsFilter('fileName', 'demo-'));
            $criteria->setLimit(100);
            $result = $this->mediaRepository->search($criteria, $context);

            $media = [];

            foreach ($result as $item) {
                /** @var MediaEntity $item */
                $media[] = [
                    'id' => $item->getId(),
                    'name' => $item->getFileName() ?? 'Unnamed Media',
                ];
            }

            return $media;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @param array<int, array{id: string, name: string}> $availableMedia
     *
     * @return array<int, string>
     */
    public function askSelectImages(SymfonyStyle $io, array $availableMedia): array
    {
        if (empty($availableMedia)) {
            $io->warning('No media available in system.');

            return [];
        }

        $selectedMediaIds = [];
        $selectionCount = 0;

        $io->section('Select product images');
        $io->text(\sprintf(
            'Select up to %d images for product media. You can stop early by choosing "Done".',
            self::SELECTED_IMAGES_COUNT
        ));

        while ($selectionCount < self::SELECTED_IMAGES_COUNT) {
            $choices = array_map(
                static fn (array $media): string => \sprintf('%s (%s)', $media['name'], $media['id']),
                $availableMedia
            );
            $choices[] = 'Done selecting images';

            $question = new ChoiceQuestion(
                \sprintf('Select image %d of %d (or Done):', $selectionCount + 1, self::SELECTED_IMAGES_COUNT),
                $choices
            );

            $selected = $io->askQuestion($question);

            if ($selected === 'Done selecting images') {
                break;
            }

            $selectedIndex = array_search($selected, $choices, true);

            if ($selectedIndex !== false) {
                $mediaId = $availableMedia[$selectedIndex]['id'];

                if (!in_array($mediaId, $selectedMediaIds, true)) {
                    $selectedMediaIds[] = $mediaId;
                    $selectionCount++;
                    $io->writeln(\sprintf('<info>✓ Selected image %d</info>', $selectionCount));
                } else {
                    $io->warning('This image was already selected. Choose a different one.');
                }
            }
        }

        if (empty($selectedMediaIds)) {
            $io->warning('No images selected.');
        } else {
            $io->success(\sprintf('Selected %d image(s) for product assignment.', \count($selectedMediaIds)));
        }

        return $selectedMediaIds;
    }

    /**
     * @return array<int, string>
     */
    public function seedTags(Context $context): array
    {
        $payload = [];
        $tagIds = [];

        foreach (self::TAG_SEEDS as $tagName) {
            $tagId = Uuid::fromStringToHex('tag|' . $tagName);
            $tagIds[] = $tagId;
            $payload[] = [
                'id' => $tagId,
                'name' => $tagName,
            ];
        }

        $this->tagRepository->upsert($payload, $context);

        return $tagIds;
    }
}
