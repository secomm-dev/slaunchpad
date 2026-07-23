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

use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\App\ResourceConnection;

/**
 * Class Revenue
 * @package Mageplaza\ExtraFee\Ui\Component\Listing\Columns
 */
class Revenue extends Column
{
    /**
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Revenue constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param PriceHelper $priceHelper
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        PriceHelper $priceHelper,
        ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        $this->priceHelper = $priceHelper;
        $this->resourceConnection = $resourceConnection;

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     *
     * @return array
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
     * Get revenue from stats table
     *
     * @param array $item
     *
     * @return string
     */
    protected function prepareItem($item)
    {
        $ruleId = (int) $item['rule_id'];
        $connection = $this->resourceConnection->getConnection();
        $statsTable = $connection->getTableName('mageplaza_extrafee_stats');

        // Read revenue from stats table
        $select = $connection->select()
            ->from($statsTable, ['revenue'])
            ->where('rule_id = ?', $ruleId);

        $total = (float) $connection->fetchOne($select);
        $total = ($total > 0) ? $total : 0.0;

        return $this->priceHelper->currency($total, true, false);
    }
}
