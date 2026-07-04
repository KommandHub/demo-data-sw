<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Command;

use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Console command to seed product property groups into Shopware.
 *
 * This command provides an automated CLI tool that:
 * - Creates product property groups with their metadata (display type, sorting, etc.)
 * - Seeds property group options (e.g., colors, sizes, materials)
 * - Supports 20+ property group categories covering all major e-commerce verticals
 *
 * Property groups enable filtering and product variant management for:
 * - Apparel (Color, Clothing Size)
 * - Footwear (Shoe Size)
 * - Electronics (Storage, RAM, Screen Size)
 * - Appliances (Power, Energy Rating)
 * - Beauty & Personal Care (Skin Type, Fragrance Family)
 * - And more specialized categories
 *
 * All property groups are created as non-sortable list types with color display
 * support where applicable (Color property group).
 *
 * Usage:
 *   php bin/console kommandhub:add-property-groups
 */
#[AsCommand('kommandhub:add-property-groups', 'Create product property groups')]
class PropertyGroupCommand extends Command
{
    /**
     * Success/Error messages.
     */
    private const SUCCESS_GROUPS_CREATED = 'Property groups created successfully. Total: %d groups with %d options.';
    private const ERROR_CREATION_FAILED = 'Failed to create property groups: %s';
    private const INFO_CREATING_GROUPS = 'Creating %d property groups with their options...';

    /**
     * Comprehensive product property group definitions.
     *
     * Structure: Each property group contains:
     * - name: Display name (required)
     * - description: Purpose and usage details
     * - displayType: How options are rendered ('text' or 'color')
     * - sortingType: Sort strategy ('alphanumeric' or 'position')
     * - filterable: Whether group appears in shop filters
     * - visibleOnProductDetailPage: Show on product detail pages
     * - options: Array of selectable values
     *
     * @var array<int, array<string, mixed>>
     */
    private const PROPERTY_GROUPS = [
        [
            'name' => 'Color',
            'description' => 'Product color',
            'displayType' => 'color',
            'sortingType' => 'alphanumeric',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Black',
                'White',
                'Grey',
                'Silver',
                'Gold',
                'Blue',
                'Navy',
                'Red',
                'Green',
                'Olive',
                'Yellow',
                'Orange',
                'Purple',
                'Pink',
                'Brown',
                'Beige',
                'Cream',
                'Khaki',
                'Wine',
                'Transparent',
            ],
        ],
        [
            'name' => 'Clothing Size',
            'description' => 'Apparel sizes',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'XS',
                'S',
                'M',
                'L',
                'XL',
                'XXL',
                '3XL',
                '4XL',
            ],
        ],
        [
            'name' => 'Shoe Size',
            'description' => 'Footwear sizes',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '37',
                '38',
                '39',
                '40',
                '41',
                '42',
                '43',
                '44',
                '45',
                '46',
            ],
        ],
        [
            'name' => 'Material',
            'description' => 'Primary product material',
            'displayType' => 'text',
            'sortingType' => 'alphanumeric',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Cotton',
                'Leather',
                'Faux Leather',
                'Polyester',
                'Linen',
                'Silk',
                'Denim',
                'Canvas',
                'Rubber',
                'Plastic',
                'Wood',
                'Metal',
                'Glass',
                'Aluminium',
                'Stainless Steel',
            ],
        ],
        [
            'name' => 'Storage',
            'description' => 'Internal storage capacity',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '16 GB',
                '32 GB',
                '64 GB',
                '128 GB',
                '256 GB',
                '512 GB',
                '1 TB',
            ],
        ],
        [
            'name' => 'RAM',
            'description' => 'Memory size',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '2 GB',
                '4 GB',
                '6 GB',
                '8 GB',
                '12 GB',
                '16 GB',
                '32 GB',
            ],
        ],
        [
            'name' => 'Screen Size',
            'description' => 'Display size',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '5.5"',
                '6.1"',
                '6.7"',
                '10"',
                '13"',
                '15.6"',
                '24"',
                '27"',
                '32"',
                '43"',
                '50"',
                '55"',
                '65"',
                '75"',
            ],
        ],
        [
            'name' => 'Weight',
            'description' => 'Package weight',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '250 g',
                '500 g',
                '750 g',
                '1 kg',
                '2 kg',
                '5 kg',
                '10 kg',
                '25 kg',
                '50 kg',
            ],
        ],
        [
            'name' => 'Volume',
            'description' => 'Liquid volume',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '250 ml',
                '500 ml',
                '750 ml',
                '1 L',
                '2 L',
                '5 L',
            ],
        ],
        [
            'name' => 'Power',
            'description' => 'Power rating',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                '500 W',
                '750 W',
                '1000 W',
                '1500 W',
                '2000 W',
                '2500 W',
                '3500 W',
            ],
        ],
        [
            'name' => 'Energy Rating',
            'description' => 'Energy efficiency',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'A',
                'A+',
                'A++',
                'A+++',
            ],
        ],
        [
            'name' => 'Connectivity',
            'description' => 'Supported connectivity options',
            'displayType' => 'text',
            'sortingType' => 'alphanumeric',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Wi-Fi',
                'Bluetooth',
                '4G LTE',
                '5G',
                'USB-C',
                'Lightning',
                'HDMI',
                'Ethernet',
                'NFC',
            ],
        ],
        [
            'name' => 'Skin Type',
            'description' => 'Suitable skin type',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'All Skin Types',
                'Dry',
                'Oily',
                'Combination',
                'Sensitive',
            ],
        ],
        [
            'name' => 'Fragrance Family',
            'description' => 'Fragrance type',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Fresh',
                'Floral',
                'Woody',
                'Oriental',
                'Citrus',
            ],
        ],
        [
            'name' => 'Vehicle Type',
            'description' => 'Compatible vehicle',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Car',
                'SUV',
                'Truck',
                'Motorcycle',
            ],
        ],
        [
            'name' => 'Paper Size',
            'description' => 'Paper dimensions',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'A3',
                'A4',
                'A5',
                'Letter',
                'Legal',
            ],
        ],
        [
            'name' => 'Country of Origin',
            'description' => 'Manufacturing country',
            'displayType' => 'text',
            'sortingType' => 'alphanumeric',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Nigeria',
                'China',
                'Germany',
                'Japan',
                'South Korea',
                'United Kingdom',
                'United States',
            ],
        ],
        [
            'name' => 'Warranty',
            'description' => 'Manufacturer warranty',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'No Warranty',
                '3 Months',
                '6 Months',
                '1 Year',
                '2 Years',
                '3 Years',
            ],
        ],
        [
            'name' => 'Condition',
            'description' => 'Product condition',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'New',
                'Refurbished',
            ],
        ],
        [
            'name' => 'Made In',
            'description' => 'Production location',
            'displayType' => 'text',
            'sortingType' => 'position',
            'filterable' => true,
            'visibleOnProductDetailPage' => true,
            'options' => [
                'Nigeria',
                'Imported',
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param EntityRepository<PropertyGroupCollection> $propertyGroupRepository Repository for property groups
     */
    public function __construct(
        private readonly EntityRepository $propertyGroupRepository
    ) {
        parent::__construct();
    }

    /**
     * Executes the console command.
     *
     * Workflow:
     * 1. Prepares property group data with generated UUIDs
     * 2. Creates property groups and their options in batches
     * 3. Provides user feedback on creation progress
     * 4. Reports total counts on successful completion
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

        try {
            // Prepare property groups with generated UUIDs and options
            $preparedGroups = $this->preparePropertyGroups();

            // Display operation progress
            $totalGroups = count($preparedGroups);
            $totalOptions = array_sum(array_map(
                static function (array $group): int {
                    /** @var array<int, mixed> $options */
                    $options = $group['options'] ?? [];

                    return count($options);
                },
                $preparedGroups
            ));

            $io->info(\sprintf(self::INFO_CREATING_GROUPS, $totalGroups));

            // Upsert all property groups to database (idempotent operation)
            $this->propertyGroupRepository->upsert($preparedGroups, $context);

            // Confirm successful completion with statistics
            $io->success(\sprintf(self::SUCCESS_GROUPS_CREATED, $totalGroups, $totalOptions));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            // Log error and provide user feedback
            $io->error(\sprintf(self::ERROR_CREATION_FAILED, $e->getMessage()));

            return Command::FAILURE;
        }
    }

    /**
     * Prepares property groups for database insertion.
     *
     * Transforms the property group definition array into a format ready for Shopware's
     * entity repository. Generates deterministic UUIDs and processes all options with
     * position-based ordering.
     *
     * Processing:
     * - Converts group name to hex-encoded UUID for consistent ID generation
     * - Creates option entries with auto-incremented position values
     * - Applies configured display and sorting types
     * - Preserves filterable and visibility flags
     *
     *
     * @return array<int, array<string, mixed>> Property groups formatted for database insertion
     */
    private function preparePropertyGroups(): array
    {
        $prepared = [];

        foreach (self::PROPERTY_GROUPS as $group) {
            $groupName = $group['name'];
            $groupId = Uuid::fromStringToHex($groupName);

            // Build the core property group entry
            $groupEntry = [
                'id' => $groupId,
                'name' => $groupName,
                'displayType' => $group['displayType'],
                'sortingType' => $group['sortingType'],
                'filterable' => $group['filterable'],
                'visibleOnProductDetailPage' => $group['visibleOnProductDetailPage'],
            ];

            // Process options with position-based ordering
            $groupEntry['options'] = $this->preparePropertyOptions(
                $groupId,
                $group['options']
            );

            $prepared[] = $groupEntry;
        }

        return $prepared;
    }

    /**
     * Prepares property options for a group.
     *
     * Converts raw option names into database entries with:
     * - Generated UUIDs based on option name
     * - Position values for consistent ordering
     * - Reference to parent group ID
     *
     * @param string $groupId The parent property group UUID
     * @param array<int, string> $options Raw option names
     *
     * @return array<int, array<string, mixed>> Formatted options with position and IDs
     */
    private function preparePropertyOptions(string $groupId, array $options): array
    {
        $preparedOptions = [];
        $position = 0;

        foreach ($options as $optionName) {
            // Create option entry with deterministic ID and position
            $preparedOptions[] = [
                'id' => Uuid::fromStringToHex($optionName . $groupId),
                'name' => $optionName,
                'position' => $position,
            ];
            $position++;
        }

        return $preparedOptions;
    }
}
