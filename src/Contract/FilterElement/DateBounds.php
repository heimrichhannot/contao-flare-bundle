<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract\FilterElement;

/**
 * The date bounds a {@see DateBoundsContract} element imposes on a date range control. A null bound is open.
 * The upper bound is exclusive: a range must end before it.
 *
 * @api
 */
final readonly class DateBounds
{
    public ?\DateTimeImmutable $min;
    public ?\DateTimeImmutable $max;

    public function __construct(?\DateTimeInterface $min = null, ?\DateTimeInterface $max = null)
    {
        $this->min = $min !== null ? \DateTimeImmutable::createFromInterface($min) : null;
        $this->max = $max !== null ? \DateTimeImmutable::createFromInterface($max) : null;
    }
}
