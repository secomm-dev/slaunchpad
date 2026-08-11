<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Ui\Component\Form;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\App\CacheInterface;

class CityOptions implements OptionSourceInterface
{
    private const CACHE_KEY_PREFIX = 'secomm_ghn_city_options_';
    private const CACHE_TAG = 'directory_region_city';
    private const CACHE_LIFETIME = 86400;

    /**
     * @var array<int, array{value: string, label: string, region_id: string}>|null
     */
    protected ?array $options = null;

    public function __construct(
        protected ResourceConnection $resourceConnection,
        protected ResolverInterface $localeResolver,
        protected CacheInterface $cache
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string, region_id: string}>
     */
    public function toOptionArray(): array
    {
        if ($this->options !== null) {
            return $this->options;
        }

        $currentLocale = $this->localeResolver->getLocale();
        $cacheKey = self::CACHE_KEY_PREFIX . $currentLocale;

        $cachedData = $this->cache->load($cacheKey);
        if ($cachedData !== false) {
            $decoded = json_decode($cachedData, true);
            if (is_array($decoded)) {
                $this->options = $decoded;
                return $this->options;
            }
        }

        $connection = $this->resourceConnection->getConnection();
        $cityTable = $this->resourceConnection->getTableName('directory_region_city');
        $cityNameTable = $this->resourceConnection->getTableName('directory_region_city_name');

        $select = $connection->select()
            ->from(['c' => $cityTable], ['city_id', 'region_id', 'default_name'])
            ->joinLeft(
                ['cn' => $cityNameTable],
                $connection->quoteInto('c.city_id = cn.city_id AND cn.locale = ?', $currentLocale),
                ['localized_name' => 'name']
            )
            ->order('COALESCE(cn.name, c.default_name) ASC');

        $rows = $connection->fetchAll($select);
        $options = [];
        $seen = [];

        foreach ($rows as $row) {
            $cityName = !empty($row['localized_name']) ? (string)$row['localized_name'] : (string)$row['default_name'];
            $key = $row['region_id'] . '_' . $row['city_id'];

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $options[] = [
                'value' => (string)$row['city_id'],
                'label' => $cityName,
                'region_id' => (string)$row['region_id']
            ];
        }

        // Merge custom/imported city_ids from location mapping table
        $mappingTable = $this->resourceConnection->getTableName('secomm_ghn_address_mapping_location');
        $mappingSelect = $connection->select()
            ->from($mappingTable, ['region_id', 'city_id', 'city_name'])
            ->where('city_id IS NOT NULL');
        $mappingRows = $connection->fetchAll($mappingSelect);

        foreach ($mappingRows as $mRow) {
            $key = $mRow['region_id'] . '_' . $mRow['city_id'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $options[] = [
                    'value' => (string)$mRow['city_id'],
                    'label' => (string)$mRow['city_name'],
                    'region_id' => (string)$mRow['region_id']
                ];
            }
        }

        $this->options = $options;
        $this->cache->save(
            json_encode($this->options),
            $cacheKey,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->options;
    }
}
