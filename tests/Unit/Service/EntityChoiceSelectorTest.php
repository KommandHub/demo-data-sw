<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Tests\Unit\Service;

use Kommandhub\DemoDataSW\Service\EntityChoiceSelector;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntity;
use Kommandhub\DemoDataSW\Tests\Unit\Fixture\DemoEntityCollection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

final class EntityChoiceSelectorTest extends TestCase
{
    public function testReturnsNullWhenNoEntitiesExist(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('search')
            ->willReturn($this->createSearchResult([]));

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(self::never())->method('askQuestion');

        $selector = new EntityChoiceSelector();
        $result = $selector->selectOne(
            $io,
            $repository,
            Context::createDefaultContext(),
            new Criteria(),
            'Select item',
            static fn (DemoEntity $entity): string => $entity->getName() ?? ''
        );

        self::assertNull($result);
    }

    public function testReturnsSelectedEntity(): void
    {
        $entity = new DemoEntity('11111111111111111111111111111111', 'Alpha');
        $collection = new DemoEntityCollection([$entity]);
        $result = $this->createSearchResult([$entity], $collection);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::once())
            ->method('search')
            ->willReturn($result);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects(self::once())
            ->method('askQuestion')
            ->with(self::isInstanceOf(ChoiceQuestion::class))
            ->willReturn('Alpha (11111111)');

        $selector = new EntityChoiceSelector();
        $selected = $selector->selectOne(
            $io,
            $repository,
            Context::createDefaultContext(),
            new Criteria(),
            'Select item',
            static fn (DemoEntity $entity): string => $entity->getName() ?? ''
        );

        self::assertSame([
            'id' => '11111111111111111111111111111111',
            'name' => 'Alpha',
        ], $selected);
    }

    /**
     * @param array<int, DemoEntity> $entities
     */
    private function createSearchResult(array $entities, ?DemoEntityCollection $collection = null): EntitySearchResult
    {
        $collection ??= new DemoEntityCollection($entities);

        return new EntitySearchResult(
            'demo_entity',
            $collection->count(),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
