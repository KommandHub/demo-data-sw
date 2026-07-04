<?php

declare(strict_types=1);

namespace Kommandhub\DemoDataSW\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class EntityChoiceSelector
{
    /**
     * @template TCollection of EntityCollection
     *
     * @param EntityRepository<TCollection> $repository
     * @param callable(Entity): string $labelResolver
     *
     * @return array{id: string, name: string}|null
     */
    public function selectOne(
        SymfonyStyle $io,
        EntityRepository $repository,
        Context $context,
        Criteria $criteria,
        string $prompt,
        callable $labelResolver
    ): ?array {
        $entities = $repository->search($criteria, $context)->getEntities();

        if ($entities->count() === 0) {
            return null;
        }

        $labelToEntity = [];

        foreach ($entities as $entity) {
            $name = $labelResolver($entity) ?: 'Unnamed entity';
            $label = \sprintf('%s (%s)', $name, substr($entity->getUniqueIdentifier(), 0, 8));
            $labelToEntity[$label] = [
                'id' => $entity->getUniqueIdentifier(),
                'name' => $name,
            ];
        }

        $question = new ChoiceQuestion($prompt, array_keys($labelToEntity));

        /** @var string $selection */
        $selection = $io->askQuestion($question);

        return $labelToEntity[$selection] ?? null;
    }
}
