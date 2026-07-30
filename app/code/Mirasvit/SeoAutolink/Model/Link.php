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



namespace Mirasvit\SeoAutolink\Model;

use Magento\Framework\DataObject\IdentityInterface;
use Mirasvit\SeoAutolink\Api\Data\LinkInterface;

/**
 * Class Link
 * @package Mirasvit\SeoAutolink\Model
 */
class Link extends \Magento\Framework\Model\AbstractModel implements IdentityInterface, LinkInterface
{
    const CACHE_TAG = 'seoautolink_link';
    /**
     * @var string
     */
    protected $_cacheTag = 'seoautolink_link';//@codingStandardsIgnoreLine
    /**
     * @var string
     */
    protected $_eventPrefix = 'seoautolink_link';//@codingStandardsIgnoreLine

    /**
     * Get identities.
     *
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG.'_'.$this->getId()];
    }

    /**
     *
     */
    protected function _construct()
    {
        $this->_init('Mirasvit\SeoAutolink\Model\ResourceModel\Link');
    }

    /**
     * @param string $keyword
     * @return bool|\Magento\Framework\DataObject
     */
    public function loadByKeyword($keyword)
    {
        $collection = $this->getCollection()
                        ->addFieldToFilter('keyword', $keyword);
        if ($collection->count() > 0) {
            return $collection->getFirstItem();
        }

        return false;
    }

    /**
     * @return string|null
     */
    public function getKeyword(): ?string
    {
        return $this->getData(self::KEYWORD);
    }

    /**
     * @param string $value
     * @return LinkInterface
     */
    public function setKeyword(?string $value): LinkInterface
    {
        return $this->setData(self::KEYWORD, $value);
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->getData(self::URL);
    }

    /**
     * @param string $value
     * @return LinkInterface
     */
    public function setUrl(?string $value): LinkInterface
    {
        return $this->setData(self::URL, $value);
    }

    /**
     * @return string|null
     */
    public function getUrlTarget(): ?string
    {
        return $this->getData(self::URL_TARGET);
    }

    /**
     * @param string|null $value
     * @return LinkInterface
     */
    public function setUrlTarget(?string $value): LinkInterface
    {
        return $this->setData(self::URL_TARGET, $value);
    }

    /**
     * @return string|null
     */
    public function getUrlTitle(): ?string
    {
        return $this->getData(self::URL_TITLE);
    }

    /**
     * @param string|null $value
     * @return LinkInterface
     */
    public function setUrlTitle(?string $value): LinkInterface
    {
        return $this->setData(self::URL_TITLE, $value);
    }

    /**
     * @return int|null
     */
    public function getIsNofollow(): ?int
    {
        $value = $this->getData(self::IS_NOFOLLOW);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $value
     * @return LinkInterface
     */
    public function setIsNofollow(?int $value): LinkInterface
    {
        return $this->setData(self::IS_NOFOLLOW, $value);
    }

    /**
     * @return int|null
     */
    public function getMaxReplacements(): ?int
    {
        $value = $this->getData(self::MAX_REPLACEMENTS);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $value
     * @return LinkInterface
     */
    public function setMaxReplacements(?int $value): LinkInterface
    {
        return $this->setData(self::MAX_REPLACEMENTS, $value);
    }

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int
    {
        $value = $this->getData(self::SORT_ORDER);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $value
     * @return LinkInterface
     */
    public function setSortOrder(?int $value): LinkInterface
    {
        return $this->setData(self::SORT_ORDER, $value);
    }

    /**
     * @return int|null
     */
    public function getOccurence(): ?int
    {
        $value = $this->getData(self::OCCURENCE);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $value
     * @return LinkInterface
     */
    public function setOccurence(?int $value): LinkInterface
    {
        return $this->setData(self::OCCURENCE, $value);
    }

    /**
     * @return int|null
     */
    public function getIsActive(): ?int
    {
        $value = $this->getData(self::IS_ACTIVE);
        return $value !== null ? (int)$value : null;
    }

    /**
     * @param int $value
     * @return LinkInterface
     */
    public function setIsActive(?int $value): LinkInterface
    {
        return $this->setData(self::IS_ACTIVE, $value);
    }

    /**
     * @return string|null
     */
    public function getActiveFrom(): ?string
    {
        return $this->getData(self::ACTIVE_FROM);
    }

    /**
     * @param string|null $value
     * @return LinkInterface
     */
    public function setActiveFrom(?string $value): LinkInterface
    {
        return $this->setData(self::ACTIVE_FROM, $value);
    }

    /**
     * @return string|null
     */
    public function getActiveTo(): ?string
    {
        return $this->getData(self::ACTIVE_TO);
    }

    /**
     * @param string|null $value
     * @return LinkInterface
     */
    public function setActiveTo(?string $value): LinkInterface
    {
        return $this->setData(self::ACTIVE_TO, $value);
    }

    public function getStoreIds(): array
    {
        return (array)$this->getData(self::STORE_IDS);
    }

    public function setStoreIds(array $value): LinkInterface
    {
        return $this->setData(self::STORE_IDS, $value);
    }

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    public function getLinkType(): string
    {
        return $this->getData(self::LINK_TYPE) ?? self::LINK_TYPE_URL;
    }

    public function setLinkType(string $value): LinkInterface
    {
        return $this->setData(self::LINK_TYPE, $value);
    }

    public function getLinkEntityId(): ?int
    {
        $value = $this->getData(self::ENTITY_ID);
        return $value !== null ? (int)$value : null;
    }

    public function setLinkEntityId(?int $value): LinkInterface
    {
        return $this->setData(self::ENTITY_ID, $value);
    }
}
