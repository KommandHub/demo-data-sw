<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class DemoEntity extends Entity
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $name = null
    ) {
        $this->setUniqueIdentifier($id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
