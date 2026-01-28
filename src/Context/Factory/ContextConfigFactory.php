<?php

namespace HeimrichHannot\FlareBundle\Context\Factory;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factory class for creating and validating context objects from serialized data.
 */
readonly class ContextConfigFactory
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface  $validator,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $className The class name of the object to create.
     * @param array<string, mixed> $data The data to create the object from.
     * @return T The created object.
     * @throws ValidationFailedException If the object is invalid.
     */
    public function create(string $className, array $data): object
    {
        // hydrate object from the array
        $object = $this->serializer->denormalize($data, $className, null, [
            // Allow non-strict types (i.e., string "1" for int 1). The validator will handle type mismatches.
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
        ]);

        // validate object
        $violations = $this->validator->validate($object);

        if ($violations->count()) {
            throw new ValidationFailedException($object, $violations);
        }

        return $object;
    }
}