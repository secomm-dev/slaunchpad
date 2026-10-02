<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 *
 * SL-003 / FEAT-005 (DEC-017): server-side validation of the VN 2-level relationship
 * for cart shipping estimation. When country = VN, the submitted ward (Magento `city`)
 * MUST belong to the submitted province (Magento `region_id`). An invalid ward is
 * neutralised (cleared on the RateRequest) so carriers never rate on a false ward.
 *
 * Reuse (DEC-019): the city/ward data + collection come from the GENERIC
 * Secomm_AddressDropdown (no data duplication). VN-specific logic only.
 *
 * Never breaks rate collection: a validation fault is logged and skipped (spec:
 * "Address API failure does not break the cart").
 */

declare(strict_types=1);

namespace Secomm\VietNamAddress\Plugin\Cart;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;

class ValidateVietNamWard
{
    /**
     * TASK-Z6SK3T: per-request ward-validity memo ("<regionId>|<ward>" => bool) — the
     * plugin runs once per carrier per rate collection, so an address change fires the
     * identical validation repeatedly within one request.
     */
    private array $wardValidMemo = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate ward(province) relationship for VN before carriers collect rates.
     *
     * @return array [RateRequest] (unchanged-ish; dest city neutralised if invalid)
     */
    public function beforeCollectRates(Shipping $subject, RateRequest $request): array
    {
        try {
            if ($request->getDestCountryId() !== 'VN') {
                return [$request];
            }

            $regionId = (string) ($request->getDestRegionId() ?? '');
            $ward = (string) ($request->getDestCity() ?? '');

            // Incomplete VN address: the frontend gates the rate request until both
            // province + ward are selected; nothing to validate server-side here.
            if ($regionId === '' || $ward === '') {
                return [$request];
            }

            $memoKey = $regionId . '|' . $ward;
            if (!isset($this->wardValidMemo[$memoKey])) {
                $this->wardValidMemo[$memoKey] = $this->isWardInRegion($regionId, $ward);
            }

            if (!$this->wardValidMemo[$memoKey]) {
                // Ward does not belong to the province -> neutralise to avoid a
                // misleading rate. Logged for visibility (do not silently guess).
                $this->logger->warning(
                    'Secomm_VietNamAddress: invalid VN ward for province; neutralising dest city.',
                    ['region_id' => $regionId, 'ward' => $ward]
                );
                $request->setDestCity('');
            }
        } catch (\Exception $e) {
            // Intentional non-fatal guard (spec: cart must not break on API failure).
            // Logged, not swallowed silently.
            $this->logger->error(
                'Secomm_VietNamAddress: VN ward validation failed; skipping validation.',
                ['exception' => (string) $e]
            );
        }

        return [$request];
    }

    /**
     * Single-statement ward-membership probe (TASK-Z6SK3T): fetchOne + limit 1 instead of
     * a full CityLocaleCollection build + getSize() COUNT. Dual-match (TASK-ADT94K): the
     * schema renderer submits the locale-resolved name (e.g. vi_VN "Hoàn Kiếm"), the
     * legacy renderer the ASCII default_name — match either.
     */
    private function isWardInRegion(string $regionId, string $ward): bool
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['d' => $this->resource->getTableName('directory_region_city')], [new \Zend_Db_Expr('1')])
            ->joinLeft(
                ['n' => $this->resource->getTableName('directory_region_city_name')],
                'n.city_id = d.city_id',
                []
            )
            ->where('d.region_id = ?', $regionId)
            ->where(
                $connection->quoteInto('d.default_name = ?', $ward)
                . ' OR ' . $connection->quoteInto('n.name = ?', $ward)
            )
            ->limit(1);

        return $connection->fetchOne($select) !== false;
    }
}
