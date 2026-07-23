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
 * @category  Mageplaza
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Block\Adminhtml\Report;

use Exception;
use Magento\Framework\Phrase;

/**
 * Class TopSocialLogin
 *
 * @package Mageplaza\SocialLoginPro\Block\Adminhtml\Report
 */
class TopSocialLogin extends AbstractClass
{
    const NAME = 'topSocialLogin';

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_SocialLoginPro::dashboard/top-social-login.phtml';

    /**
     * @return mixed
     * @throws Exception
     */
    public function prepareData()
    {
        if ($this->_helperData->isEnabled()) {
            return false;
        }

        $data            = [];
        $collection      = $this->getCollection();
        $totalSocialUser = $this->addDateFilter($collection, 'main_table.social_created_at')->getSize();

        foreach ($this->getSocialTypesArray() as $key => $type) {
            $storeId    = $this->_helperData->getStoreId();
            $collection = $this->filterOrder($type);
            $collection = $this->addDateFilter($collection, 'top_social_login.created_at');
            $collection->addFieldToFilter('store_id', $storeId);

            if ($this->getNumberUser($type) > 0) {
                $data[$key] = [
                    'user'    => $this->getNumberUser($type),
                    'type'    => $type,
                    'percent' => $totalSocialUser > 0 ? number_format(
                        ($this->getNumberUser($type) / $totalSocialUser) * 100,
                        2
                    ) : 0,
                    'order'   => 0,
                    'amount'  => 0
                ];
            }

            if ($collection->getData() && count($data) > 0) {
                foreach ($collection->getData() as $value) {
                    if ($value['total_refunded'] === null && $value['total_canceled'] === null) {
                        $data[$key]['order']++;
                        $data[$key]['amount'] += $value['grand_total'];
                    }
                }
            }
        }

        usort(
            $data,
            function ($firstValue, $secondValue) {
                return $secondValue['percent'] <=> $firstValue['percent'];
            }
        );

        return count($data) > 0 ? array_slice($data, 0, 5) : false;
    }

    /**
     * @return Phrase|string
     */
    public function getTitle()
    {
        return __('Top Social Login');
    }

    /**
     * @return string
     */
    public function getTableId()
    {
        return 'top_social_login';
    }
}
