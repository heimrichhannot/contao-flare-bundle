<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Type;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\CoreBundle\String\SimpleTokenParser;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\DcaContract;
use HeimrichHannot\FlareBundle\Contract\ListType\DataContainerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListType;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsListType(type: self::TYPE)]
class GenericDataContainerListType extends AbstractListType implements DataContainerContract, DcaContract
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

    protected function transformListModel(ListModel $model, ConfigBuilder $config): void
    {
        $config->set('genericPageMeta', true);
    }

    public function buildDca(DcaBuilder $dca, DcaContext $context): void
    {
        $listModel = $context->listModel;

        if (!$listModel->hasParent) {
            $dca->palette(self::DEFAULT_PALETTE);
            return;
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

        $dca->palette($pm->applyToString(self::DEFAULT_PALETTE));
    }
}
