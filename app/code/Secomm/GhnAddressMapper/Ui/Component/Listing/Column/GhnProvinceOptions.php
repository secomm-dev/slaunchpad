<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;

class GhnProvinceOptions implements OptionSourceInterface
{
    private const CACHE_KEY = 'secomm_ghn_province_options';
    private const CACHE_TAG = 'secomm_giaohangnhanh_province';
    private const CACHE_LIFETIME = 86400;

    /** @var array<int, array{value: string, label: string}>|null */
    protected ?array $options = null;

    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected CacheInterface $cache
    ) {
    }

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

        $this->cache->save(
            json_encode($this->options),
            self::CACHE_KEY,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->options;
    }
}
