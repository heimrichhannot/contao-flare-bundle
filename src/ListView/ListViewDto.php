<?php /** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace HeimrichHannot\FlareBundle\ListView;

use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\ListView\Resolver\ListViewResolverInterface;
use HeimrichHannot\FlareBundle\Model\ListModel;
use Symfony\Component\Form\FormInterface;

class ListViewDto
{
    private iterable $entries;
    private FormInterface $formComponent;

    public function __construct(
        private readonly ListModel                 $listModel,
        private readonly ListViewResolverInterface $resolver,
        private ?string                            $formName = null,
    ) {}

    public function getListModel(): ListModel
    {
        return $this->listModel;
    }

    public function getFormName(): string
    {
        if (!isset($this->formName)) {
            $this->formName = $this->resolver->getFormName($this);
        }

        return $this->formName;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEntries(): iterable
    {
        if (!isset($this->entries)) {
            $this->entries = $this->resolver->getEntries($this);
        }

        return $this->entries;
    }

    /**
     * @throws FilterException
     */
    public function getFormComponent(): FormInterface
    {
        if (!isset($this->formComponent)) {
            $this->formComponent = $this->resolver->getForm($this);
        }

        return $this->formComponent;
    }
}