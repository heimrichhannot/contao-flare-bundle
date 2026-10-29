<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\List;

use HeimrichHannot\FlareBundle\Exception\FilterException;

/**
 * The failure of a filter that fails its list as a whole (SPEC_FILTER_FORMS.md §8.1).
 *
 * @api
 */
final readonly class FilterFailure
{
    /**
     * @param string|null $source The failing filter's source, e.g. "tl_flare_filter.42"; null for an inline filter.
     * @param string $message The failure as logged, naming the filter and its list.
     * @param \Exception|null $exception The cause, if any (none e.g. for a duplicate alias).
     */
    public function __construct(
        public ?string     $source,
        public string      $message,
        public ?\Exception $exception = null,
    ) {}

    /**
     * The cause for an editor, without the log's framing: the message of a filter exception wrapped for the query
     * ("[FLARE] Query denied: …"), else the exception's message, else the failure's message.
     */
    /**
     * The exception a list fails with as a whole because of the given failures (SPEC_FILTER_FORMS.md §8.1).
     *
     * @param list<self> $failures
     */
    public static function toListException(array $failures, string $source, string $method): FilterException
    {
        return new FilterException(
            \sprintf('[FLARE] The list has failed: %s', \implode('; ', \array_map(
                static fn (self $failure): string => $failure->message,
                $failures,
            ))),
            method: $method,
            source: $source,
        );
    }

    public function getReason(): string
    {
        $exception = $this->exception;

        if ($exception === null) {
            return $this->message;
        }

        if ($exception instanceof FilterException && $exception->getPrevious() instanceof FilterException) {
            return $exception->getPrevious()->getMessage();
        }

        return $exception->getMessage();
    }
}
