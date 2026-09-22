<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Giao hang nhanh
 */
namespace Secomm\GiaoHangNhanh\Model\Service\Request;

use Magento\Framework\Exception\NoSuchEntityException;
use Secomm\GiaoHangNhanh\Model\Exception\GhnLocationMappingException;

class ServicesDataBuilder extends AbstractDataBuilder
{
    /**
     * @param array $buildSubject
     * @return array
     * @throws NoSuchEntityException
     * @throws GhnLocationMappingException when the destination mapping is unavailable (BUG-JBX3H9)
     */
    public function build(array $buildSubject)
    {
        $fromDistrict = (int) $this->config->getValue('district');
        $toDistrict = 0;
        if (isset($buildSubject['rate_request'])) {
            $location = $this->resolveGhnLocation((int)$buildSubject['rate_request']->getData("dest_region_id"), (string) $buildSubject['rate_request']->getData("dest_city"));
            $toDistrict = $location['toDistrictId'];
        }

        $data = [
            self::TOKEN => $this->config->getValue('api_token'),
            self::FROM_DISTRICT => $fromDistrict,
            self::TO_DISTRICT => $toDistrict,
            self::SHOP_ID => (int)$this->config->getValue('shop_id')
        ];

        return $data;
    }
}
