<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\FlareContainer;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

class FlareContainer
{
    private iterable $entries;
    private FormInterface $formComponent;

    public function __construct(
        private readonly ListModel                $listModel,
        private readonly FlareContainerStrategies $strategies,
        private ?string                           $formName = null,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFormName(): string
    {
        if (!isset($this->formName)) {
            $this->formName = $this->strategies->getFormName($this);
        }

        return $this->formName;
    }

    /**
     * @throws FilterException
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(): iterable
    {
        if (!isset($this->entries)) {
            $this->entries = $this->strategies->getEntries($this);
        }

        return $this->entries;
    }

    /**
     * @throws FilterException
     */
    public function getFormComponent(): FormInterface
    {
        if (!isset($this->formComponent)) {
            $this->formComponent = $this->strategies->getForm($this);
        }

        return $this->formComponent;
    }
}