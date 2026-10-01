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

namespace Mageplaza\RMA\Ui\Component\Listing\Columns;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class CustomerGroup
 * @package Mageplaza\RMA\Ui\Component\Listing\Columns
 */
class Website extends Column
{
    /**
     * @var WebsiteRepositoryInterface
     */
    protected $_websiteRepository;

    /**
     * CustomerGroup constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        WebsiteRepositoryInterface $websiteRepository,
        array $components = [],
        array $data = []
    ) {
        $this->_websiteRepository = $websiteRepository;

        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
    }

    /**
     * Prepare Data Source
     *
     * @param array[][] $dataSource
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = $this->_prepareItem($item, $this->_websiteRepository);
            }
        }

        return $dataSource;
    }

    /**
     * @param $dataSource
     *
     * @return array
     */
    public function getWebsiteIds($dataSource)
    {
        return explode(',', $dataSource);
    }

    /**
     * Get customer group name
     *
     * @param array $item
     * @param WebsiteRepositoryInterface $website
     *
     * @return string
     * @throws NoSuchEntityException
     */
    protected function _prepareItem(array $item, $website)
    {
        $content = '';
        if (isset($item['websites'])) {
            $websiteIds = $this->getWebsiteIds($item['websites']);
            $lastItem = end($websiteIds);
            foreach ($websiteIds as $websiteId) {
                $content .= ($lastItem !== $websiteId)
                    ? $website->getById($websiteId)->getName() . ', '
                    : $website->getById($websiteId)->getName();
            }
        }

        return $content;
    }
}
