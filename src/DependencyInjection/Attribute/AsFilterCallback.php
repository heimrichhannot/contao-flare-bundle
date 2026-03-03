<?php

namespace HeimrichHannot\FlareBundle\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsFilterCallback extends AsFlareCallback {}
