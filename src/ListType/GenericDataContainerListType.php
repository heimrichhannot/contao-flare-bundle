<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\ListType;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Contract\Config\PaletteConfig;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsListType(type: self::TYPE, palette: self::DEFAULT_PALETTE)]
class GenericDataContainerListType extends AbstractListType implements DataContainerContract
{
    public const TYPE = 'flare_generic_dc';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem;{parent_legend},hasParent;
        {meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat
        PALETTE;

    public function __construct(
        private readonly HtmlDecoder         $htmlDecoder,
        private readonly SimpleTokenParser   $simpleTokenParser,
        private readonly TranslatorInterface $trans,
    ) {}

    protected function getHtmlDecoder(): HtmlDecoder
    {
        return $this->htmlDecoder;
    }

    protected function getSimpleTokenParser(): SimpleTokenParser
    {
        return $this->simpleTokenParser;
    }

    public function getDataContainerName(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }

    public function getPalette(PaletteConfig $config): ?string
    {
        $listModel = $config->getListModel();

        if (!$listModel->hasParent) {
            return null;
        }

        $pm = PaletteManipulator::create()
            ->addField('fieldPid', 'parent_legend', PaletteManipulator::POSITION_APPEND)
            ->addField('whichPtable', 'parent_legend', PaletteManipulator::POSITION_APPEND)
        ;

        $table = $listModel->dc;

        $inferrer = new PtableInferrer($listModel, $listModel->dc);

        try
        {
            $ptable = $inferrer->getInferredPtable();

            Message::addInfo(match (true) {
                $inferrer->isAutoInferable() && $ptable => $this->trans->trans('infer_ptable.auto', [
                    '%table%' => $table,
                    '%field%' => $listModel->fieldPid,
                    '%ptable%' => $ptable,
                ], 'flare'),
                $inferrer->isAutoDynamicPtable() => $this->trans->trans('infer_ptable.dynamic', [
                    '%table%' => $table,
                ], 'flare'),
                default => $this->trans->trans('infer_ptable.invalid', [
                    '%table%' => $table,
                    '%field%' => $listModel->fieldPid,
                ], 'flare'),
            });
        }
        catch (InferenceException $e)
        {
            Message::addError($e->getMessage());
        }

        if (!$inferrer->isAutoInferable())
        {
            $listModel->whichPtable_disableAutoOption();
        }

        return $pm->applyToString(self::DEFAULT_PALETTE);
    }
}