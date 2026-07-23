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
 * @package     Mageplaza_ExtraFee
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ExtraFee\Ui\Component\Listing\Columns;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\ResourceModel\Group\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class CustomerGroup
 * @package Mageplaza\ExtraFee\Ui\Component\Listing\Columns
 */
class CustomerGroup extends Column
{
    /**
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var Collection
     */
    protected $groupCollection;

    /**
     * CustomerGroup constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param GroupRepositoryInterface $groupRepository
     * @param Collection $groupCollection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        GroupRepositoryInterface $groupRepository,
        Collection $groupCollection,
        array $components = [],
        array $data = []
    ) {
        $this->groupRepository = $groupRepository;
        $this->groupCollection = $groupCollection;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items']) && is_array($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = $this->prepareItem($item);
            }
        }

        return $dataSource;
    }

    /**
     * Get customer group name
     *
     * @param array $item
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function prepareItem(array $item)
    {
        $content = [];

        if (isset($item['customer_groups'])) {
            $groupIds = $item['customer_groups'] || $item['customer_groups'] === '0'
                ? explode(',', $item['customer_groups']) : [];
            foreach ($groupIds as $groupId) {
                if (in_array($groupId, $this->groupCollection->getAllIds(), true)) {
                    $content[] = $this->groupRepository->getById($groupId)->getCode();
                }
            }
        }
        if (empty($content) || count($content) === $this->groupCollection->count()) {
            return __('All Customer Groups');
        }

        return implode(', ', $content);
    }
}
