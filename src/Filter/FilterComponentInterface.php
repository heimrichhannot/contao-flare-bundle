<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Filter;

/**
 * A component of a filter that is registered by type and owns its own config: a filter element
 * ({@see Element\FilterElementInterface}) or a filter control ({@see Control\FilterControlInterface}).
 *
 * Config is resolved per component by {@see Resolver\FilterTransformerResolver} and
 * {@see Resolver\FilterOptionsResolver}, depending on the optional contracts the component implements.
 *
 * @api
 */
interface FilterComponentInterface {}
