<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Ajax;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Locale\ResolverInterface;
use Psr\Log\LoggerInterface;

class CascadingOptions extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected JsonFactory $resultJsonFactory,
        protected ResourceConnection $resourceConnection,
        protected ResolverInterface $localeResolver,
        protected LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = [];
        $type = $this->getRequest()->getParam('type');
        $parentId = $this->getRequest()->getParam('parent_id');
        $connection = $this->resourceConnection->getConnection();

        try {
            switch ($type) {
                case 'city':
                    $select = $connection->select()
                        ->from(['c' => $this->resourceConnection->getTableName('directory_region_city')], ['city_id', 'default_name', 'region_id'])
                        ->joinLeft(
                            ['cn' => $this->resourceConnection->getTableName('directory_region_city_name')],
                            $connection->quoteInto('c.city_id = cn.city_id AND cn.locale = ?', $this->localeResolver->getLocale()),
                            ['localized_name' => 'name']
                        )
                        ->where('region_id = ?', (int)$parentId)
                        ->order('COALESCE(cn.name, c.default_name) ASC');
                    $rows = $connection->fetchAll($select);
                    foreach ($rows as $row) {
                        $label = !empty($row['localized_name']) ? $row['localized_name'] : $row['default_name'];
                        $result[] = [
                            'value' => (string)$row['city_id'],
                            'label' => $label
                        ];
                    }
                    break;

                case 'ward':
                    $cityId = (int)$parentId;
                    if ($cityId) {
                        $select = $connection->select()
                            ->from($this->resourceConnection->getTableName('directory_city_sub_city'), ['sub_city_id', 'default_name'])
                            ->where('city_id = ?', $cityId)
                            ->order('default_name ASC');
                        $rows = $connection->fetchAll($select);
                        foreach ($rows as $row) {
                            $result[] = [
                                'value' => $row['default_name'],
                                'label' => $row['default_name']
                            ];
                        }
                    }
                    break;

                case 'ghn_district':
                    $select = $connection->select()
                        ->from($this->resourceConnection->getTableName('secomm_giaohangnhanh_district'), ['district_id', 'district_name'])
                        ->where('province_id = ?', (int)$parentId)
                        ->order('district_name ASC');
                    $rows = $connection->fetchAll($select);
                    foreach ($rows as $row) {
                        $result[] = [
                            'value' => $row['district_id'],
                            'label' => $row['district_name'] . ' (' . $row['district_id'] . ')'
                        ];
                    }
                    break;

                case 'ghn_ward':
                    $select = $connection->select()
                        ->from($this->resourceConnection->getTableName('secomm_giaohangnhanh_ward'), ['ward_code', 'ward_name'])
                        ->where('district_id = ?', (int)$parentId)
                        ->order('ward_name ASC');
                    $rows = $connection->fetchAll($select);
                    foreach ($rows as $row) {
                        $result[] = [
                            'value' => $row['ward_code'],
                            'label' => $row['ward_name'] . ' (' . $row['ward_code'] . ')'
                        ];
                    }
                    break;
            }
        } catch (\Exception $e) {
            $this->logger->error('GHN Address Mapper: CascadingOptions error', ['exception' => $e]);
            $result = ['error' => __('An error occurred while loading options. Please try again.')];
        }

        return $this->resultJsonFactory->create()->setData($result);
    }
}
