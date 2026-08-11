<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\Component\Form;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;

class GhnWardOptions implements OptionSourceInterface
{
    private const CACHE_KEY = 'secomm_ghn_ward_options';
    private const CACHE_TAG = 'secomm_giaohangnhanh_ward';
    private const CACHE_LIFETIME = 86400;

    /**
     * @var array<int, array{value: string, label: string, ghn_district_id: string}>|null
     */
    protected ?array $options = null;

    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected CacheInterface $cache
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string, ghn_district_id: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $cachedData = $this->cache->load(self::CACHE_KEY);
        if ($cachedData !== false) {
            $decoded = json_decode($cachedData, true);
            if (is_array($decoded)) {
                $this->options = $decoded;
                return $this->options;
            }
        }

        $connection = $this->resourceConnection->getConnection();
        $wardTable = $this->resourceConnection->getTableName('secomm_giaohangnhanh_ward');
        $districtTable = $this->resourceConnection->getTableName('secomm_giaohangnhanh_district');

        $select = $connection->select()
            ->from(['w' => $wardTable], ['ward_code', 'ward_name'])
            ->joinInner(
                ['d' => $districtTable],
                'w.district_id = d.entity_id',
                ['district_id']
            )
            ->order('w.ward_name ASC');

        $rows = $connection->fetchAll($select);
        $options = [];

        foreach ($rows as $row) {
            $options[] = [
                'value' => (string)$row['ward_code'],
                'label' => sprintf('%s (%s)', $row['ward_name'], $row['ward_code']),
                'ghn_district_id' => (string)$row['district_id']
            ];
        }

        $this->options = $options;
        $this->cache->save(
            json_encode($this->options),
            self::CACHE_KEY,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->options;
    }
}
