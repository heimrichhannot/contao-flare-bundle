<?php

namespace HeimrichHannot\FlareBundle\Contract;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface OptionsInterface
{
    public function configureOptions(OptionsResolver $resolver): void;
}