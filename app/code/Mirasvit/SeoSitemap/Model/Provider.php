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
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoSitemap\Model;

use Magento\Framework\Model\AbstractModel;
use Mirasvit\SeoSitemap\Api\Data\ProviderInterface;
use Mirasvit\SeoSitemap\Model\ResourceModel\Provider as ResourceModel;

class Provider extends AbstractModel implements ProviderInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    public function getId(): ?int
    {
        return parent::getId() ? (int)parent::getId() : null;
    }

    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    public function setName(?string $name): ProviderInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getUrl(): ?string
    {
        return $this->getData(self::URL);
    }

    public function setUrl(?string $url): ProviderInterface
    {
        return $this->setData(self::URL, $url);
    }

    public function getPriority(): ?string
    {
        return $this->getData(self::PRIORITY);
    }

    public function setPriority(?string $priority): ProviderInterface
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    public function getFrequency(): ?string
    {
        return $this->getData(self::FREQUENCY);
    }

    public function setFrequency(?string $frequency): ProviderInterface
    {
        return $this->setData(self::FREQUENCY, $frequency);
    }

    public function getIsActive(): ?int
    {
        $value = $this->getData(self::IS_ACTIVE);
        return $value !== null ? (int)$value : null;
    }

    public function setIsActive(?int $isActive): ProviderInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    public function getStoreIds(): array
    {
        $storeIds = $this->getData(self::STORE_IDS);
        if (is_array($storeIds)) {
            return $storeIds;
        }
        return $storeIds ? array_map('intval', explode(',', (string)$storeIds)) : [];
    }

    public function setStoreIds(array $storeIds): ProviderInterface
    {
        return $this->setData(self::STORE_IDS, implode(',', $storeIds));
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}
