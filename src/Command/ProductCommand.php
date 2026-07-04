<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Command;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Kommandhub\DemoDataSW\Service\DeterministicValueGenerator;
use Kommandhub\DemoDataSW\Service\ProductDataProvider;
use Kommandhub\DemoDataSW\Service\ProductPayloadBuilder;

#[AsCommand('kommandhub:add-demo-products', 'Create demo products using existing categories and properties')]
class ProductCommand extends Command
{
    private const PRODUCTS_PER_CATEGORY_MIN = 10;
    private const PRODUCTS_PER_CATEGORY_MAX = 30;
    private const UPSERT_BATCH_SIZE = 100;

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly ProductDataProvider $productDataProvider,
        private readonly ProductPayloadBuilder $productPayloadBuilder,
        private readonly DeterministicValueGenerator $deterministicValueGenerator
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        $manufacturer = $this->productDataProvider->askManufacturer($io, $context);

        if ($manufacturer === null) {
            return Command::FAILURE;
        }

        $categories = $this->productDataProvider->fetchTargetCategories($context);

        if ($categories === []) {
            $io->error('No target categories found. Run kommandhub:add-demo-categories first.');

            return Command::FAILURE;
        }

        $groupedPropertyOptionIds = $this->productDataProvider->fetchGroupedPropertyOptionIds($context);

        if ($groupedPropertyOptionIds === []) {
            $io->error('No target property options found. Run kommandhub:add-property-groups first.');

            return Command::FAILURE;
        }

        $taxId = $this->productDataProvider->fetchTaxId($context);

        if ($taxId === null) {
            $io->error('No tax configuration found. Please create at least one tax first.');

            return Command::FAILURE;
        }

        $salesChannelIds = $this->productDataProvider->fetchSalesChannelIds($context);

        $availableMedia = $this->productDataProvider->fetchAvailableMedia($context);
        $selectedMediaIds = $this->productDataProvider->askSelectImages($io, $availableMedia);

        if ($selectedMediaIds === []) {
            $io->warning('No images selected. Proceeding without product images.');
        }

        $tagIds = $this->productDataProvider->seedTags($context);

        $products = [];

        foreach ($categories as $category) {
            $productsToCreate = $this->deterministicValueGenerator->int(
                'products-per-category|' . $category['id'],
                self::PRODUCTS_PER_CATEGORY_MIN,
                self::PRODUCTS_PER_CATEGORY_MAX
            );

            for ($i = 1; $i <= $productsToCreate; ++$i) {
                $products[] = $this->productPayloadBuilder->build(
                    $category['id'],
                    $category['name'],
                    $category['parentId'],
                    $manufacturer['id'],
                    $taxId,
                    $groupedPropertyOptionIds,
                    $salesChannelIds,
                    $selectedMediaIds,
                    $tagIds,
                    $i
                );
            }
        }

        foreach (array_chunk($products, self::UPSERT_BATCH_SIZE) as $batch) {
            $this->productRepository->upsert($batch, $context);
        }

        $io->success(\sprintf(
            'Created %d products across %d categories using manufacturer "%s".',
            count($products),
            count($categories),
            $manufacturer['name']
        ));

        return Command::SUCCESS;
    }
}
