<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Contract;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void;
}
