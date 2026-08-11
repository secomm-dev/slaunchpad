<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;

class GhnProvinceOptions implements OptionSourceInterface
{
    protected $options = null;

    public function __construct(
        protected ResourceConnection $resourceConnection
    ) {
    }

    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from($this->resourceConnection->getTableName('secomm_giaohangnhanh_province'), ['province_id', 'province_name'])
                ->order('province_name ASC');
            $rows = $connection->fetchAll($select);
            $this->options = [];
            foreach ($rows as $row) {
                $this->options[] = [
                    'value' => $row['province_id'],
                    'label' => $row['province_name']
                ];
            }
        }
        return $this->options;
    }
}
