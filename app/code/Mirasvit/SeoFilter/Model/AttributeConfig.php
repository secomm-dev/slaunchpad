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

namespace Mirasvit\SeoFilter\Model;

use Magento\Framework\Model\AbstractModel;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;

class AttributeConfig extends AbstractModel implements AttributeConfigInterface
{
    public function getId(): ?int
    {
        return $this->getData(self::ID) ? (int)$this->getData(self::ID) : null;
    }

    public function getAttributeCode(): string
    {
        return (string)$this->getData(self::ATTRIBUTE_CODE);
    }

    public function setAttributeCode(string $value): AttributeConfigInterface
    {
        return $this->setData(self::ATTRIBUTE_CODE, $value);
    }

    public function getAttributeStatus(): int
    {
        return (int)($this->getData(self::ATTRIBUTE_STATUS) ?? AttributeConfigInterface::SEO_STATUS_DEFAULT);
    }

    public function setAttributeStatus(int $value): AttributeConfigInterface
    {
        return $this->setData(self::ATTRIBUTE_STATUS, $value);
    }

    protected function _construct()
    {
        $this->_init(ResourceModel\AttributeConfig::class);
    }

}
