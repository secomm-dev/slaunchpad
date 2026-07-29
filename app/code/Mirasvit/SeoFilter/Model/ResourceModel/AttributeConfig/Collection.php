<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoFilter\Model\ResourceModel\AttributeConfig;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;
use Mirasvit\SeoFilter\Model\AttributeConfig;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(
            AttributeConfig::class,
            \Mirasvit\SeoFilter\Model\ResourceModel\AttributeConfig::class
        );

        $this->_idFieldName = AttributeConfigInterface::ID;
    }
}
