<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

final class DemoEntity extends Entity
{
    /**
     * @var string[]|null
     */
    private ?array $breadcrumb = null;

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

    /**
     * @return string[]|null
     */
    public function getBreadcrumb(): ?array
    {
        return $this->breadcrumb;
    }

    /**
     * @param string[]|null $breadcrumb
     */
    public function setBreadcrumb(?array $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }
}
