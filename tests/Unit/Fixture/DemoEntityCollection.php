<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<DemoEntity>
 */
final class DemoEntityCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DemoEntity::class;
    }

    /**
     * @param array<int, DemoEntity> $entities
     */
    public function __construct(array $entities = [])
    {
        parent::__construct($entities);
    }
}
