<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Command;

use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
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

/**
 * Console command to seed footer navigation categories into Shopware.
 *
 * The command uses an existing root category named "Footer" when one is available.
 * If it is not set yet, the command creates it and then seeds the footer columns
 * and links beneath it.
 */
#[AsCommand('kommandhub:add-footer-categories', 'Create footer categories')]
class FooterCategoryCommand extends Command
{
    private const CATEGORY_STATUS_ACTIVE = true;

    private const CATEGORY_STATUS_VISIBLE = true;

    private const ROOT_FOOTER_CATEGORY_NAME = 'Footer';

    private const INFO_CREATING_CATEGORIES = 'Creating footer categories under "%s"...';

    private const INFO_CREATING_ROOT_CATEGORY = 'Creating root footer category "%s".';

    private const SUCCESS_CATEGORIES_CREATED = 'Footer categories created successfully.';

    private const PROMPT_USE_EXISTING = 'Do you want to use an existing root category for footer links?';

    private const PROMPT_SELECT_PARENT = 'Please select the root footer category:';

    private const BREADCRUMB_SEPARATOR = ' > ';

    /**
     * Footer category hierarchy definition.
     *
     * @var array<int, array{name: string, active?: bool, visible?: bool, children?: array<int, array<string, mixed>>}>
     */
    private const CATEGORIES = [
        [
            'name' => 'ShopHub',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'About ShopHub', 'active' => true, 'visible' => true],
                ['name' => 'Our Story', 'active' => true, 'visible' => true],
                ['name' => 'Careers', 'active' => true, 'visible' => true],
                ['name' => 'Press & Media', 'active' => true, 'visible' => true],
                ['name' => 'Partner Program', 'active' => true, 'visible' => true],
                ['name' => 'Become a Vendor', 'active' => true, 'visible' => true],
                ['name' => 'Affiliate Program', 'active' => true, 'visible' => true],
                ['name' => 'Blog', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Customer Services',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Help Center', 'active' => true, 'visible' => true],
                ['name' => 'Track Your Order', 'active' => true, 'visible' => true],
                ['name' => 'Shipping & Delivery', 'active' => true, 'visible' => true],
                ['name' => 'Returns & Refunds', 'active' => true, 'visible' => true],
                ['name' => 'Payment Methods', 'active' => true, 'visible' => true],
                ['name' => 'FAQs', 'active' => true, 'visible' => true],
                ['name' => 'Contact Us', 'active' => true, 'visible' => true],
                ['name' => 'Report an Issue', 'active' => true, 'visible' => true],
            ],
        ],
        [
            'name' => 'Policies & Legal',
            'active' => true,
            'visible' => true,
            'children' => [
                ['name' => 'Privacy Policy', 'active' => true, 'visible' => true],
                ['name' => 'Terms & Conditions', 'active' => true, 'visible' => true],
                ['name' => 'Cookie Policy', 'active' => true, 'visible' => true],
                ['name' => 'Accessibility', 'active' => true, 'visible' => true],
                ['name' => 'Warranty Policy', 'active' => true, 'visible' => true],
                ['name' => 'Vendor Terms', 'active' => true, 'visible' => true],
                ['name' => 'Buyer Protection', 'active' => true, 'visible' => true],
                ['name' => 'Sitemap', 'active' => true, 'visible' => true],
            ],
        ],
    ];

    /**
     * @param EntityRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly EntityRepository $categoryRepository,
        private readonly EntityChoiceSelector $entityChoiceSelector
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', null));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $rootCategoryResult = $this->categoryRepository->search($criteria, $context);

        $footerRoot = null;

        if ($rootCategoryResult->count() > 0 && $io->confirm(self::PROMPT_USE_EXISTING, false)) {
            $footerRoot = $this->entityChoiceSelector->selectOne(
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
        }

        if ($footerRoot === null) {
            $io->info(\sprintf(self::INFO_CREATING_ROOT_CATEGORY, self::ROOT_FOOTER_CATEGORY_NAME));
            $footerRoot = $this->createFooterRootCategory($context);
        }

        $io->info(\sprintf(self::INFO_CREATING_CATEGORIES, $footerRoot['name']));

        $this->categoryRepository->upsert([[
            'id' => $footerRoot['id'],
            'name' => $footerRoot['name'],
            'children' => $this->prepareCategories(self::CATEGORIES),
        ]], $context);

        $io->success(self::SUCCESS_CATEGORIES_CREATED);

        return Command::SUCCESS;
    }

    /**
     * @return array{id: string, name: string, active: bool, visible: bool}
     */
    private function createFooterRootCategory(Context $context): array
    {
        $footerRoot = [
            'id' => Uuid::fromStringToHex(self::ROOT_FOOTER_CATEGORY_NAME),
            'name' => self::ROOT_FOOTER_CATEGORY_NAME,
            'active' => self::CATEGORY_STATUS_ACTIVE,
            'visible' => self::CATEGORY_STATUS_VISIBLE,
        ];

        $this->categoryRepository->upsert([$footerRoot], $context);

        return $footerRoot;
    }

    /**
     * @param array<int, array{name: string, active?: bool, visible?: bool, children?: array<int, mixed>}> $categories
     *
     * @return array<int, array<string, mixed>>
     */
    private function prepareCategories(array $categories): array
    {
        $prepared = [];

        foreach ($categories as $category) {
            $name = $category['name'];

            $entry = [
                'id' => Uuid::fromStringToHex((string)$name),
                'name' => $name,
                'active' => $category['active'] ?? self::CATEGORY_STATUS_ACTIVE,
                'visible' => $category['visible'] ?? self::CATEGORY_STATUS_VISIBLE,
            ];

            if (!empty($category['children'])) {
                /** @var array<int, array{name: string, active?: bool, visible?: bool, children?: array<int, mixed>}> $children */
                $children = $category['children'];
                $entry['children'] = $this->prepareCategories($children);
            }

            $prepared[] = $entry;
        }

        return $prepared;
    }
}
