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
 * Class SocialLoginChart
 * @package Mageplaza\SocialLoginPro\Block\Adminhtml\Report
 */
class SocialLoginChart extends AbstractClass
{
    const NAME = 'socialLoginChart';

    /**
     * @var string
     */
    protected $_template = 'Mageplaza_SocialLoginPro::dashboard/social-login-chart.phtml';

    /**
     * @return false|string
     * @throws Exception
     */
    public function prepareChart()
    {
        if ($this->_helperData->isEnabled()) {
            return false;
        }

        $data            = [];
        $collection      = $this->getCollection();
        $totalSocialUser = $this->addDateFilter($collection, 'main_table.social_created_at')->getSize();

        foreach ($this->getSocialTypesArray() as $type) {
            if ($this->getNumberUser($type) > 0) {
                $data[] = [
                    'provider' => $type,
                    'percent'  => $totalSocialUser > 0 ? number_format(
                        ($this->getNumberUser($type) / $totalSocialUser) * 100,
                        2
                    ) : 0,
                ];
            }
        }

        return count($data) > 0 ? json_encode($data) : false;
    }

    /**
     * @return Phrase|string
     */
    public function getTitle()
    {
        return __('Social Login Chart');
    }
}
