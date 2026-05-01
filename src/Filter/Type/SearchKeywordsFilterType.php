<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter\Type;

use HeimrichHannot\FlareBundle\ConfigProvider;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchKeywordsFilterType extends AbstractFilterType
{
    public function __construct(
        private readonly ConfigProvider $configProvider,
    ) {}

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('value')->required()->allowedTypes('string');
        $resolver->define('columns')->required()->allowedTypes('array');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
        $columns = \array_map($builder->column(...), $options['columns']);
        $searchTermGroups = \array_values(\preg_split('/\s+OR\s+/i', $options['value']));
        $or = [];

        foreach ($searchTermGroups ?: [] as $i => $searchTermGroup)
        {
            if (!$searchTerms = $this->makeTerms($searchTermGroup)) {
                return;
            }

            $and = [];

            foreach (\array_values($searchTerms) as $j => $term)
            {
                $param = ':term_' . $i . '_' . $j;

                $and[] = $builder->expr()->or(...\array_map(
                    static fn (string $column): string => $builder->expr()->like($column, $param),
                    $columns
                ));

                $builder->setParameter($param, '%' . $term . '%');
            }

            $or[] = $builder->expr()->and(...$and);
        }

        $builder->where($builder->expr()->or(...$or));
    }

    private function makeTerms(string $text): array
    {
        $text = (string) \mb_strtolower($text);
        $text = \preg_replace('/[^\p{L}\p{Nd}-]+/u', ' ', $text);
        $text = \preg_replace('/\s+/', ' ', $text);
        $terms = \array_unique(\array_filter(\array_map('\trim', \explode(' ', \trim($text)))));
        $stopWords = $this->configProvider->getStopWords();

        return $stopWords ? \array_diff($terms, $stopWords) : $terms;
    }
}