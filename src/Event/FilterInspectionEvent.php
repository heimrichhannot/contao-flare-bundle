<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Event;

use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by the backend's filter inspector once per saved filter row of a list, published or not, in sorting
 * order, after the framework's own checks. Listeners add warnings: hints on a valid setup that may not do what the
 * editor expects; a warning never fails the list. Filters without a row (added by a list driver or a listener) get
 * no event: there is no row to show a warning on.
 *
 * An \Exception of a listener is reported as a programming error on the row.
 */
final class FilterInspectionEvent extends Event
{
    /** @var list<array{message: string, icon: ?string}> */
    private array $warnings = [];

    /**
     * @param FilterModel $model The inspected filter row.
     * @param ListCompound|null $list The list as the runtime builds it; null if it cannot be built.
     * @param FilterCompound|null $filter The row's filter: the one in the list if the list has it, so that a
     *   {@see ListDriverBuildListEvent} listener's replacement is what listeners see, else the one built for the row
     *   alone; null if it could not be built.
     * @param list<FilterIssue> $issues The row's issues found so far.
     */
    public function __construct(
        public readonly FilterModel     $model,
        public readonly ?ListCompound   $list,
        public readonly ?FilterCompound $filter,
        public readonly array           $issues,
    ) {}

    /**
     * Adds a warning to the row.
     *
     * @param string $message The already translated message; shown as the row's tooltip and on the edit form.
     * @param string|null $icon The name of the lucide icon to show the warning with; null, or a name the bundle has
     *   no icon for, shows the warning's default icon.
     */
    public function addWarning(string $message, ?string $icon = null): void
    {
        $this->warnings[] = ['message' => $message, 'icon' => $icon];
    }

    /**
     * @return list<array{message: string, icon: ?string}> The warnings added, in the order added.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
