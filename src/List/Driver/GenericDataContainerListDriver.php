<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Driver;

use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\ListDriver\OnSubmitDcContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\Filter\Element\PublishedFilterElement;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsListDriver(type: self::TYPE)]
class GenericDataContainerListDriver extends AbstractListDriver implements OnSubmitDcContract
{
    public const TYPE = 'flare_generic_dc';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem;{parent_legend},hasParent;
        {meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat
        PALETTE;

    public function __construct(
        private readonly Connection          $connection,
        private readonly TranslatorInterface $trans,
        private readonly ListContainer       $listContainer,
    ) {}

    public function resolveDcOnSubmit(array $row, DataContainer $dc): string
    {
        return $row['dc'] ?? '';
    }

    protected function transformListModel(ConfigBuilder $config, ListModel $model): void
    {
        $config->set('genericPageMeta', true);
    }

    public function buildDca(DcaBuilderInterface $dca, DcaContext $context): void
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

        $inferrer = new PtableInferrer($listModel, $listModel->dc);

        try
        {
            $table = $listModel->dc;
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

        $this->checkPublishedFilter($listModel);

        $dca->palette($pm->applyToString(self::DEFAULT_PALETTE));
    }

    private function checkPublishedFilter(ListModel $listModel): void
    {
        $displayTable = $listModel->dc;
        Controller::loadDataContainer($displayTable);

        if (!isset($GLOBALS['TL_DCA'][$displayTable]['fields']['published'])) {
            return;
        }

        if ($this->listContainer->hasFilterConfigured($listModel, PublishedFilterElement::TYPE)) {
            return;
        }

        Message::addInfo($this->trans->trans('list.info.no_published_filter', [
            '%target%' => "{$displayTable}.published",
        ], 'flare'));
    }
}
