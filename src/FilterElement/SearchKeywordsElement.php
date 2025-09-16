<?php

namespace HeimrichHannot\FlareBundle\FilterElement;

use Contao\StringUtil;
use HeimrichHannot\FlareBundle\Contract\FilterElement\FormTypeOptionsContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Dto\ContentContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use Symfony\Component\Form\Extension\Core\Type\TextType;

#[AsFilterElement(
    alias: SearchKeywordsElement::TYPE,
    palette: '{filter_legend},columnsGeneric;{form_legend},placeholder',
    formType: TextType::class,
    scopes: [ContentContext::CONTEXT_LIST],
    isTargeted: true,
)]
class SearchKeywordsElement implements FormTypeOptionsContract
{
    public const TYPE = 'flare_search_keywords';

    public function __invoke(FilterContext $context, FilterQueryBuilder $qb): void
    {
        $submittedData = $context->getSubmittedData();
        if (!$submittedData || !\is_string($submittedData)) {
            return;
        }

        $filterModel = $context->getFilterModel();
        if (!$columns = StringUtil::deserialize($filterModel->columnsGeneric, true)) {
            return;
        }

        $columns = \array_map($qb->column(...), $columns);

        if (empty($searchTerms = $this->makeTerms($submittedData))) {
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
        $terms = \explode(' ', \trim($text));
        $stopWords = [
            'und', 'oder', 'nicht', 'kein', 'alle', 'allem', 'aller', 'alles', 'auch', 'beide', 'beiden', 'beider',
            'beides', 'da', 'damit', 'danach', 'darauf', 'darum', 'das', 'dass', 'dein', 'deine', 'deinem', 'deiner',
            'deines', 'der', 'des', 'dessen', 'die', 'dies', 'dieser', 'dieses', 'doch', 'ein', 'eine', 'einem',
            'einer', 'eines', 'eins', 'euer', 'eure', 'eurem', 'eurer', 'eures', 'für', 'gegen', 'gegenüber', 'haben',
            'hat', 'hatte', 'hatten', 'hier', 'hinter', 'ich', 'ihm', 'ihn', 'ihnen', 'ihre', 'ihrem', 'ihrer', 'ihres',
            'im', 'in', 'indem', 'ins', 'ist', 'ja', 'jed', 'jede', 'jedem', 'jeder', 'jedes', 'jener', 'jetzt', 'kann',
            'kein', 'keine', 'keinem', 'keiner', 'keines', 'konnte', 'könnte', 'machen', 'and', 'or'
        ];

        return \array_diff($terms, $stopWords);
    }

    public function getFormTypeOptions(FilterContext $context, ChoicesBuilder $choices): array
    {
        $options = [
            'label' => 'label.text',
            'required' => false,
        ];

        if ($placeholder = $context->getFilterModel()?->placeholder) {
            $options['attr']['placeholder'] = $placeholder;
        }

        return $options;
    }
}