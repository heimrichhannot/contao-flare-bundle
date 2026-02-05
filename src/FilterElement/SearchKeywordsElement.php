<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\IntrinsicValueContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Event\FilterElementFormTypeOptionsEvent;
use HeimrichHannot\FlareBundle\Filter\FilterInvocation;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Specification\FilterDefinition;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[AsFilterElement(
    type: self::TYPE,
    formType: TextType::class,
    isTargeted: true,
)]
class SearchKeywordsElement extends AbstractFilterElement implements IntrinsicValueContract
{
    public const TYPE = 'flare_search_keywords';

    public function __invoke(FilterInvocation $inv, FilterQueryBuilder $qb): void
    {
        $value = $inv->getValue();
        if (!$value || !\is_string($value)) {
            return;
        }

        if (!$columns = StringUtil::deserialize($inv->filter->columnsGeneric, true)) {
            return;
        }

        $columns = \array_map($qb->column(...), $columns);

        if (!$searchTerms = $this->makeTerms($value)) {
            return;
        }

        $and = [];
        foreach (\array_values($searchTerms) as $i => $term)
        {
            $param = ':term_' . $i;

            $and[] = $qb->expr()->or(...\array_map(
                static fn(string $column): string => $qb->expr()->like($column, $param),
                $columns
            ));

            $qb->setParameter($param, '%' . $term . '%');
        }

        $qb->whereAnd(...$and);
    }

    private function makeTerms(string $text): array
    {
        $text = (string) \mb_strtolower($text);
        $text = \preg_replace('/[^\p{L}\p{Nd}-]+/u', ' ', $text);
        $text = \preg_replace('/\s+/', ' ', $text);
        $terms = \array_unique(\explode(' ', \trim($text)));
        $stopWords = [
            'und', 'oder', 'nicht', 'kein', 'alle', 'allem', 'aller', 'alles', 'auch', 'beide', 'beiden', 'beider',
            'beides', 'da', 'damit', 'danach', 'darauf', 'darum', 'das', 'dass', 'dein', 'deine', 'deinem', 'deiner',
            'deines', 'der', 'des', 'dessen', 'die', 'dies', 'dieser', 'dieses', 'doch', 'ein', 'eine', 'einem',
            'einer', 'eines', 'eins', 'euer', 'eure', 'eurem', 'eurer', 'eures', 'für', 'gegen', 'gegenüber', 'haben',
            'hat', 'hatte', 'hatten', 'hier', 'hinter', 'ich', 'ihm', 'ihn', 'ihnen', 'ihre', 'ihrem', 'ihrer', 'ihres',
            'im', 'in', 'indem', 'ins', 'ist', 'ja', 'jed', 'jede', 'jedem', 'jeder', 'jedes', 'jener', 'jetzt', 'kann',
            'kein', 'keine', 'keinem', 'keiner', 'keines', 'konnte', 'könnte', 'machen', 'and', 'or',
        ];

        return \array_diff($terms, $stopWords);
    }

    public function onFormTypeOptionsEvent(FilterElementFormTypeOptionsEvent $event): void
    {
        $event->options['label'] = 'label.text';
        $event->options['required'] = false;

        if ($label = $event->filterDefinition->label) {
            $event->options['label'] = $label;
        }

        if ($placeholder = $event->filterDefinition->placeholder) {
            $event->options['attr']['placeholder'] = $placeholder;
        }
    }

    public function getIntrinsicValue(ListSpecification $list, FilterDefinition $filter): ?string
    {
        return $filter->prefill ?: null;
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $palette = '{filter_legend},columnsGeneric,prefill';

        if ($config->getFilterModel()->intrinsic) {
            return $palette;
        }

        return $palette . ';{form_legend},label,placeholder';
    }
}