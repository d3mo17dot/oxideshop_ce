<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\EshopCommunity\Internal\Framework\Module\Configuration\DataObject;

use ArrayIterator;
use IteratorAggregate;
use RecursiveArrayIterator;

class ModuleTemplateExtensionsChain extends RecursiveArrayIterator
{
    public const NAME = 'templateExtensions';

    /**
     * @return string
     */
    public function getName(): string
    {
        return self::NAME;
    }

}
