<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.scomm.vn)
 * See COPYING.txt for license details.
 *
 * SL-013 / FEAT-007 (DEC-019/025): server-side validation of the VN 2-level ward(province)
 * relationship when a VN address origin is saved in Store Information or Shipping Origin.
 *
 * When country = VN, the submitted ward (Magento `city`) MUST belong to the submitted
 * province (Magento `region_id`). An invalid ward is neutralised (cleared in the posted
 * config groups) so a malformed ward is never persisted into core_config_data.
 *
 * Mirrors Plugin/Customer/Address/ValidateVietNamWard (SL-011) and the cart estimate
 * precedent Plugin/Cart/ValidateVietNamWard (SL-003/FEAT-005): gate country = VN, reuse the
 * GENERIC Secomm_AddressDropdown collection (no data duplication — DEC-019), and never
 * break the save: a validation fault is logged and skipped (non-fatal).
 *
 * Adminhtml-scoped (etc/adminhtml/di.xml). The generic dropdown that renders the ward
 * select lives in Secomm_AddressDropdown (country-agnostic, data-driven); only the
 * VN-specific rule is owned here.
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Plugin\Adminhtml\Config;

use Magento\Config\Model\Config;
use Psr\Log\LoggerInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityLocaleCollectionFactory;

class ValidateVietNamWard
{
    /**
     * Map config section -> group that carries a VN address origin (SL-013).
     */
    private const ORIGIN_GROUP_BY_SECTION = [
        // Stores -> Configuration -> General -> Store Information (PDF/print origin)
        'general' => 'store_information',
        // Stores -> Configuration -> Sales -> Shipping Settings -> Origin (carrier/TableRate origin)
        'shipping' => 'origin',
    ];

    public function __construct(
        private readonly CityLocaleCollectionFactory $cityCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate ward(province) for a VN origin before config is saved. Neutralises an
     * invalid ward so it is never persisted.
     *
     * @param Config $subject
     * @return void
     */
    public function beforeSave(Config $subject): void
    {
        try {
            $sectionId = (string) $subject->getSection();
            if (!isset(self::ORIGIN_GROUP_BY_SECTION[$sectionId])) {
                return;
            }

            $groupId = self::ORIGIN_GROUP_BY_SECTION[$sectionId];
            $groups = (array) $subject->getGroups();
            $fields = $groups[$groupId]['fields'] ?? null;
            if (!is_array($fields)) {
                return;
            }

            $countryId = $this->fieldValue($fields, 'country_id');
            if ($countryId !== 'VN') {
                return;
            }

            $regionId = $this->fieldValue($fields, 'region_id');
            $ward = $this->fieldValue($fields, 'city');

            // Incomplete VN address: nothing to validate server-side; let Magento handle it.
            if ($regionId === '' || $ward === '') {
                return;
            }

            $collection = $this->cityCollectionFactory->create();
            $collection->addFieldToFilter('region_id', $regionId);
            // TASK-ADT94K: the schema renderer submits the locale-resolved name (e.g. vi_VN
            // "Hoàn Kiếm"), the legacy renderer the ASCII default_name — match either.
            $connection = $collection->getConnection();
            $collection->getSelect()->where(
                $connection->quoteInto('main_table.default_name = ?', $ward)
                . ' OR ' . $connection->quoteInto('rname.name = ?', $ward)
            );
            $collection->setPageSize(1)->setCurPage(1);

            if ($collection->getSize() === 0) {
                // Ward does not belong to the province -> neutralise to avoid persisting
                // a false ward. Logged for visibility (do not silently guess).
                $this->logger->warning(
                    'Secomm_VietNamAddress: invalid VN ward for province on config save; '
                    . 'neutralising city for ' . $sectionId . '/' . $groupId . '.',
                    ['section' => $sectionId, 'group' => $groupId, 'region_id' => $regionId, 'ward' => $ward]
                );
                $groups[$groupId]['fields']['city']['value'] = '';
                $subject->setGroups($groups);
            }
        } catch (\Exception $e) {
            // Intentional non-fatal guard: a validation fault must never block the save.
            // Logged, not swallowed silently.
            $this->logger->error(
                'Secomm_VietNamAddress: VN ward validation failed on config save; skipping validation.',
                ['exception' => (string) $e]
            );
        }
    }

    /**
     * Read a scalar field value from the posted config groups fields array.
     *
     * @param array $fields
     * @param string $name
     * @return string
     */
    private function fieldValue(array $fields, string $name): string
    {
        $value = $fields[$name]['value'] ?? '';
        return is_array($value) ? '' : trim((string) $value);
    }
}
