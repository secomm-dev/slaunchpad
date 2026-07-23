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
 * @category    Mageplaza
 * @package     Mageplaza_ThankYouPage
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\ThankYouPage\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Class NewsletterPageStyle
 * @package Mageplaza\ThankYouPage\Model\Config\Source
 */
class NewsletterPageStyle implements OptionSourceInterface
{
    const STYLE1 = 'style1';
    const STYLE2 = 'style2';
    const STYLE3 = 'style3';

    /**
     * @var Repository
     */
    private $_assetRepo;

    /**
     * NewsletterPageStyle constructor.
     *
     * @param Repository $assetRepo
     */
    public function __construct(Repository $assetRepo)
    {
        $this->_assetRepo = $assetRepo;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $style = [
            [
                'value' => self::STYLE1,
                'label' => 'Style 1'
            ],
            [
                'value' => self::STYLE2,
                'label' => 'Style 2'
            ],
            [
                'value' => self::STYLE3,
                'label' => 'Style 3'
            ]
        ];

        return $style;
    }

    /**
     * @return array
     */
    public function getImageUrls()
    {
        $urls = [];
        foreach ($this->toOptionArray() as $template) {
            $urls[$template['value']] = $this->_assetRepo->getUrl('Mageplaza_ThankYouPage::images/' . $template['value'] . '.jpg');
        }

        return $urls;
    }
}
