<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List\Driver;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\DataContainer;
use Contao\Message;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contract\ListDriver\DataContainerContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Exception\InferenceException;
use HeimrichHannot\FlareBundle\InferPtable\PtableInferrer;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsListDriver(type: self::TYPE)]
class GenericDataContainerListDriver extends AbstractListDriver implements DataContainerContract
{
    public const TYPE = 'flare_generic_dc';
    public const DEFAULT_PALETTE = <<<'PALETTE'
        {data_container_legend},dc,fieldAutoItem;{parent_legend},hasParent;
        {meta_legend},metaTitleFormat,metaDescriptionFormat,metaRobotsFormat
        PALETTE;

    public function __construct(
        private readonly TranslatorInterface $trans,
    ) {}

    public function resolveDataContainerTable(array $row, DataContainer $dc): string
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
