<?php

namespace HeimrichHannot\FlareBundle\Projector;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class ConfigFactory
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
        // 1. array -> object (hydration)
        // Allow non-strict types (i.e., string "1" for int 1).
        // The validator will handle the type mismatch.
        $object = $this->serializer->denormalize($data, $className, null, [
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
        ]);

        // 2. object validation
        $violations = $this->validator->validate($object);

        if (count($violations) > 0) {
            throw new ValidationFailedException($object, $violations);
        }

        return $object;
    }
}