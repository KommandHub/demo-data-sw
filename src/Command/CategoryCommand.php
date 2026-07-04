<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Command;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageCollection;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;

/**
 * Console command to seed demo product categories into Shopware.
 *
 * This command provides an interactive CLI tool that allows administrators to:
 * - Select a parent category from existing categories in the system
 * - Populate it with a pre-defined hierarchical structure of demo categories
 * - Supports nested categories (up to 3 levels deep)
 *
 * The demo categories cover major e-commerce verticals including fashion, electronics,
 * home & living, supermarket items, beauty, automotive, tools, sports, office supplies,
 * and health & wellness.
 *
 * Usage:
 *   php bin/console kommandhub:add-demo-categories
 */
#[AsCommand('kommandhub:add-demo-categories', 'Kommandhub Category Command')]
class CategoryCommand extends Command
{
    /**
     * Default category status constants.
     */
    private const CATEGORY_STATUS_ACTIVE = true;

    private const CATEGORY_STATUS_VISIBLE = true;

    /**
     * Error messages.
     */
    private const ERROR_NO_CATEGORIES_FOUND = 'No categories found in the system.';
    private const ERROR_NO_CMS_PAGES_FOUND = 'No CMS pages found in the system.';
    private const INFO_CREATING_CATEGORIES = 'Creating demo categories under "%s"...';
    private const INFO_USING_CMS_PAGE = 'Assigning CMS page "%s" to created categories.';
    private const SUCCESS_CATEGORIES_CREATED = 'Demo categories created successfully.';

    /**
     * Prompt messages.
     */
    private const PROMPT_SELECT_PARENT = 'Please select the parent category where demo categories should be added:';
    private const PROMPT_SELECT_CMS_PAGE = 'Please select the CMS page to assign to created categories:';
    private const BREADCRUMB_SEPARATOR = ' > ';

    /**
     * Demo category hierarchy definition.
     *
     * Structure: Each top-level category can contain multiple subcategories (level 2),
     * which can contain further subcategories (level 3). Uses associative arrays for clarity,
     * with 'active' and 'visible' defaulting to true if omitted.
     *
     * @var array<int, array<string, mixed>>
     */
    private const CATEGORIES = [
        [
            'name' => 'Fashion & Apparel',
            'active' => true,
            'visible' => true,
            'children' => [
                [
                    'name' => "Men's Fashion",
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Shirts & Polos', 'active' => true, 'visible' => true],
                        ['name' => 'Native Wear', 'active' => true, 'visible' => true],
                        ['name' => 'Jeans & Trousers', 'active' => true, 'visible' => true],
                        ['name' => 'Shoes & Sneakers', 'active' => true, 'visible' => true],
                        ['name' => 'Underwear', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => "Women's Fashion",
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Dresses', 'active' => true, 'visible' => true],
                        ['name' => 'African Wear', 'active' => true, 'visible' => true],
                        ['name' => 'Shoes & Heels', 'active' => true, 'visible' => true],
                        ['name' => 'Handbags', 'active' => true, 'visible' => true],
                        ['name' => 'Jewelry', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Kids Fashion',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Boys', 'active' => true, 'visible' => true],
                        ['name' => 'Girls', 'active' => true, 'visible' => true],
                        ['name' => 'Baby Clothing', 'active' => true, 'visible' => true],
                        ['name' => 'School Wear', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Accessories',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Watches', 'active' => true, 'visible' => true],
                        ['name' => 'Belts', 'active' => true, 'visible' => true],
                        ['name' => 'Sunglasses', 'active' => true, 'visible' => true],
                        ['name' => 'Caps & Hats', 'active' => true, 'visible' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Phones & Electronics',
            'active' => true,
            'visible' => true,
            'children' => [
                [
                    'name' => 'Mobile Phones',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Android Phones', 'active' => true, 'visible' => true],
                        ['name' => 'iPhones', 'active' => true, 'visible' => true],
                        ['name' => 'Feature Phones', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Phone Accessories',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Cases', 'active' => true, 'visible' => true],
                        ['name' => 'Chargers', 'active' => true, 'visible' => true],
                        ['name' => 'Power Banks', 'active' => true, 'visible' => true],
                        ['name' => 'Headphones', 'active' => true, 'visible' => true],
                        ['name' => 'Screen Protectors', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Computers',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Laptops', 'active' => true, 'visible' => true],
                        ['name' => 'Desktops', 'active' => true, 'visible' => true],
                        ['name' => 'Monitors', 'active' => true, 'visible' => true],
                        ['name' => 'Storage Devices', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Networking',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Routers', 'active' => true, 'visible' => true],
                        ['name' => 'Modems', 'active' => true, 'visible' => true],
                        ['name' => 'Cables', 'active' => true, 'visible' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Home, Kitchen & Living',
            'active' => true,
            'visible' => true,
            'children' => [
                [
                    'name' => 'Kitchen',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Blenders', 'active' => true, 'visible' => true],
                        ['name' => 'Microwaves', 'active' => true, 'visible' => true],
                        ['name' => 'Air Fryers', 'active' => true, 'visible' => true],
                        ['name' => 'Cookers', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Home Appliances',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Fans', 'active' => true, 'visible' => true],
                        ['name' => 'Air Conditioners', 'active' => true, 'visible' => true],
                        ['name' => 'Generators', 'active' => true, 'visible' => true],
                        ['name' => 'Inverters & Solar', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Furniture',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Beds', 'active' => true, 'visible' => true],
                        ['name' => 'Sofas', 'active' => true, 'visible' => true],
                        ['name' => 'Tables', 'active' => true, 'visible' => true],
                        ['name' => 'Chairs', 'active' => true, 'visible' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Supermarket',
            'active' => true,
            'visible' => true,
            'children' => [
                [
                    'name' => 'Food Staples',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Rice', 'active' => true, 'visible' => true],
                        ['name' => 'Beans', 'active' => true, 'visible' => true],
                        ['name' => 'Flour', 'active' => true, 'visible' => true],
                        ['name' => 'Cooking Oil', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Beverages',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Soft Drinks', 'active' => true, 'visible' => true],
                        ['name' => 'Water', 'active' => true, 'visible' => true],
                        ['name' => 'Juice', 'active' => true, 'visible' => true],
                    ],
                ],
                [
                    'name' => 'Household Essentials',
                    'active' => true,
                    'visible' => true,
                    'children' => [
                        ['name' => 'Detergents', 'active' => true, 'visible' => true],
                        ['name' => 'Tissue & Paper', 'active' => true, 'visible' => true],
                        ['name' => 'Cleaning Supplies', 'active' => true, 'visible' => true],
                    ],
                ],
            ],
        ],
        [
            'name' => 'Beauty & Personal Care',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Skincare', 'active' => true, 'visible' => true],
                ['name' => 'Hair Care', 'active' => true, 'visible' => true],
                ['name' => 'Makeup', 'active' => true, 'visible' => true],
                ['name' => 'Fragrances', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Baby, Kids & Toys',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Diapers & Wipes', 'active' => true, 'visible' => true],
                ['name' => 'Baby Food', 'active' => true, 'visible' => true],
                ['name' => 'Toys & Games', 'active' => true, 'visible' => true],
                ['name' => 'Maternity', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Automotive',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Car Parts', 'active' => true, 'visible' => true],
                ['name' => 'Tyres', 'active' => true, 'visible' => true],
                ['name' => 'Batteries', 'active' => true, 'visible' => true],
                ['name' => 'Car Accessories', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Tools & Hardware',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Power Tools', 'active' => true, 'visible' => true],
                ['name' => 'Hand Tools', 'active' => true, 'visible' => true],
                ['name' => 'Plumbing', 'active' => true, 'visible' => true],
                ['name' => 'Electrical', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Sports & Outdoors',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Fitness Equipment', 'active' => true, 'visible' => true],
                ['name' => 'Team Sports', 'active' => true, 'visible' => true],
                ['name' => 'Camping & Outdoor', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Office & Business',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Stationery', 'active' => true, 'visible' => true],
                ['name' => 'Printers & Ink', 'active' => true, 'visible' => true],
                ['name' => 'Packaging Materials', 'active' => true, 'visible' => true],
                ['name' => 'Office Furniture', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Health & Wellness',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Vitamins & Supplements', 'active' => true, 'visible' => true],
                ['name' => 'Medical Supplies', 'active' => true, 'visible' => true],
                ['name' => 'Personal Health', 'active' => true, 'visible' => true],
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param EntityRepository<CategoryCollection> $categoryRepository The Shopware category entity repository
     * @param EntityRepository<CmsPageCollection> $cmsPageRepository The Shopware CMS page entity repository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $cmsPageRepository,
        private readonly EntityChoiceSelector $entityChoiceSelector
    ) {
        parent::__construct();
    }

    /**
     * Executes the console command.
     *
     * Workflow:
     * 1. Fetches all root-level categories from the database
     * 2. Presents user with an interactive selection menu
     * 3. Validates selection and prepares demo categories
     * 4. Upserts the category hierarchy to the database
     *
     * @param InputInterface $input The input interface
     * @param OutputInterface $output The output interface
     *
     * @return int Command exit code (0 for success, 1 for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        // Fetch root-level categories (parentId = null) from the system
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $categories = $this->categoryRepository->search($criteria, $context);

        if ($categories->count() === 0) {
            $io->error(self::ERROR_NO_CATEGORIES_FOUND);

            return Command::FAILURE;
        }

        $parent = $this->entityChoiceSelector->selectOne(
            $io,
            $this->categoryRepository,
            $context,
            $criteria,
            self::PROMPT_SELECT_PARENT,
            static function ($category): string {
                /** @var CategoryEntity $category */
                /** @var string[] $breadcrumb */
                $breadcrumb = $category->getBreadcrumb() ?: [(string)$category->getName()];

                return implode(self::BREADCRUMB_SEPARATOR, $breadcrumb);
            }
        );

        if ($parent === null) {
            $io->error(self::ERROR_NO_CATEGORIES_FOUND);

            return Command::FAILURE;
        }

        $cmsPage = $this->askCmsPage($io, $context);

        if ($cmsPage === null) {
            return Command::FAILURE;
        }

        // Provide user feedback about the operation
        $io->info(\sprintf(self::INFO_CREATING_CATEGORIES, $parent['name']));
        $io->info(\sprintf(self::INFO_USING_CMS_PAGE, $cmsPage['name']));

        // Prepare categories with generated UUIDs
        $preparedCategories = $this->prepareCategories(self::CATEGORIES, $cmsPage['id']);

        // Upsert the prepared categories hierarchy under the selected parent
        $this->categoryRepository->upsert([[
            'id' => $parent['id'],
            'children' => $preparedCategories,
        ]], $context);

        // Confirm successful completion
        $io->success(self::SUCCESS_CATEGORIES_CREATED);

        return Command::SUCCESS;
    }

    /**
     * Recursively prepares categories for database insertion.
     *
     * Transforms the category definition array into a format ready for Shopware's
     * entity repository. Generates deterministic UUIDs based on category names
     * to ensure idempotent operations (the same name always produces the same UUID).
     *
     * Processing:
     * - Converts category name to hex-encoded UUID for consistent ID generation
     * - Applies default values for 'active' and 'visible' flags (both true)
     * - Recursively processes nested children categories
     *
     * @param array<int, array{name: string, active?: bool, visible?: bool, children?: array<int, mixed>}> $categories The raw category definitions
     *
     * @return array<int, array<string, mixed>> Categories formatted for database insertion
     */
    private function prepareCategories(array $categories, string $cmsPageId): array
    {
        $prepared = [];

        foreach ($categories as $category) {
            $name = $category['name'];

            // Build category entry with defaults and generated UUID
            $entry = [
                'id' => Uuid::fromStringToHex((string)$name),
                'name' => $name,
                'active' => $category['active'] ?? self::CATEGORY_STATUS_ACTIVE,
                'visible' => $category['visible'] ?? self::CATEGORY_STATUS_VISIBLE,
                'cmsPageId' => $cmsPageId,
            ];

            // Recursively process child categories if they exist
            if (!empty($category['children'])) {
                /** @var array<int, array{name: string, active?: bool, visible?: bool, children?: array<int, mixed>}> $children */
                $children = $category['children'];
                $entry['children'] = $this->prepareCategories($children, $cmsPageId);
            }

            $prepared[] = $entry;
        }

        return $prepared;
    }

    /**
     * Prompts the user to select a CMS page for new categories.
     *
     * @return array{id: string, name: string}|null
     */
    private function askCmsPage(SymfonyStyle $io, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('type', 'product_list')); // Filter for CMS pages only

        $cmsPage = $this->entityChoiceSelector->selectOne(
            $io,
            $this->cmsPageRepository,
            $context,
            $criteria,
            self::PROMPT_SELECT_CMS_PAGE,
            static function ($cmsPage): string {
                /** @var CmsPageEntity $cmsPage */
                return $cmsPage->getName() ?? 'Unnamed CMS Page';
            }
        );

        if ($cmsPage === null) {
            $io->error(self::ERROR_NO_CMS_PAGES_FOUND);

            return null;
        }

        return $cmsPage;
    }
}
