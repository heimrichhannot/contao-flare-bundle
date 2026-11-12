<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Exception;

use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * An intent built from a content element and its list violates its constraints: the list cannot be run as configured.
 * The validator's {@see ValidationFailedException} is the previous exception.
 *
 * @internal This exception is thrown internally and should not be
 *           thrown by userland code. You may catch it, however.
 */
class IntentException extends FlareException
{
    public static function fromViolations(
        IntentInterface                  $intent,
        ConstraintViolationListInterface $violations,
        ?string                          $method = null,
    ): self {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return new self(
            message: \sprintf('Invalid %s intent: %s', $intent::getIntentType(), \implode('; ', $messages)),
            previous: new ValidationFailedException($intent, $violations),
            method: $method,
        );
    }
}
