<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;

class RegionOptions implements OptionSourceInterface
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
                ->from($this->resourceConnection->getTableName('directory_country_region'), ['region_id', 'default_name'])
                ->where('country_id = ?', 'VN')
                ->order('default_name ASC');
            $rows = $connection->fetchAll($select);
            $this->options = [];
            foreach ($rows as $row) {
                $this->options[] = [
                    'value' => $row['region_id'],
                    'label' => $row['default_name']
                ];
            }
        }
        return $this->options;
    }
}
