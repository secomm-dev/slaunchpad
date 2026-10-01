<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_RMA
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\RMA\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Mageplaza\RMA\Model\ResourceModel\Template as TemplateResource;

/**
 * Class Template
 * @method string getCreatedAt()
 * @method string getTitle()
 * @method string getContent()
 * @method array getContents()
 * @method array getUpdateContents()
 * @package Mageplaza\RMA\Model
 */
class Template extends AbstractModel
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'mageplaza_rma_template';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'mageplaza_rma_template';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'mageplaza_rma_template';

    /**
     * @var string
     */
    protected $_idFieldName = 'template_id';

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var TemplateResource
     */
    protected $_templateResource;

    /**
     * Template constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param StoreManagerInterface $storeManager
     * @param TemplateResource $templateResource
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        StoreManagerInterface $storeManager,
        TemplateResource $templateResource,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_storeManager     = $storeManager;
        $this->_templateResource = $templateResource;

        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(TemplateResource::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @return array
     */
    public function getStoreViewTemplates()
    {
        if (!$this->hasData('templates')) {
            $templates = $this->_templateResource->getStoreViewTemplates($this);
            $this->setData('templates', $templates);
        }

        return (array)$this->_getData('templates');
    }

    /**
     * Getter for template contents per store
     *
     * @return array
     */
    public function getStoreContents()
    {
        if ($this->hasData('store_contents')) {
            return $this->_getData('store_contents');
        }
        $contents = $this->_templateResource->getStoreContents($this);
        $this->setData('store_contents', $contents);

        return $contents;
    }

    /**
     * Get status label by store
     *
     * @param string|int $storeId
     *
     * @return Phrase|string
     * @throws NoSuchEntityException
     */
    public function getStoreContent($storeId = null)
    {
        if (!$storeId) {
            $storeId = $this->_storeManager->getStore()->getId();
        }
        $contents = $this->getStoreContents();
        if (isset($contents[$storeId])) {
            return $contents[$storeId];
        }

        return $this->getContent();
    }
}
