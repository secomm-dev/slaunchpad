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

namespace Mageplaza\ThankYouPage\Helper;

use Liquid\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Block\Registration;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class LiquidFilter
 * @package Mageplaza\ThankYouPage\Helper
 */
class LiquidFilter
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var Registration
     */
    protected $registration;

    /**
     * @var UrlInterface
     */
    protected $_urlBuilder;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * Reference to the current context object
     *
     * @var Context
     */
    public $context;

    /**
     * LiquidFilter constructor.
     *
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param StoreManagerInterface $storeManager
     * @param Registration $registration
     * @param UrlInterface $url
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        StoreManagerInterface $storeManager,
        Registration $registration,
        UrlInterface $url
    ) {
        $this->productRepository = $productRepository;
        $this->priceCurrency     = $priceCurrency;
        $this->_storeManager     = $storeManager;
        $this->registration      = $registration;
        $this->_urlBuilder       = $url;
    }

    /**
     * @return array
     */
    public function getFiltersMethods()
    {
        return array_keys($this->getFilters());
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        $filters = [
            'abs'            => ['label' => __('Abs'), 'params' => []],
            'append'         => ['label' => __('Append'), 'params' => [['label' => __('Append'), 'defVal' => '']]],
            'at_least'       => ['label' => __('At Least'), 'params' => [['label' => __('At Least'), 'defVal' => '']]],
            'at_most'        => ['label' => __('At Most'), 'params' => [['label' => __('At Most'), 'defVal' => '']]],
            'capitalize'     => ['label' => __('Capitalize'), 'params' => []],
            'ceil'           => ['label' => __('Ceil'), 'params' => []],
            'date'           => ['label' => __('Date'), 'params' => [['label' => __('Date Format'), 'defVal' => '']]],
            'default'        => ['label' => __('Default'), 'params' => [['label' => __('Default'), 'defVal' => '']]],
            'divided_by'     => [
                'label'  => __('Divided By'),
                'params' => [['label' => __('Divided By'), 'defVal' => '']]
            ],
            'downcase'       => ['label' => __('Down Case'), 'params' => []],
            'escape'         => ['label' => __('Escape'), 'params' => []],
            'escape_once'    => ['label' => __('Escape once'), 'params' => []],
            'floor'          => ['label' => __('Floor'), 'params' => []],
            'join'           => ['label' => __('Join'), 'params' => [['label' => __('Join By'), 'defVal' => '']]],
            'lstrip'         => ['label' => __('Left Trim'), 'params' => []],
            'minus'          => ['label' => __('Minus'), 'params' => [['label' => __('Minus'), 'defVal' => '']]],
            'modulo'         => ['label' => __('Modulo'), 'params' => [['label' => __('Divided By'), 'defVal' => '']]],
            'newline_to_br'  => ['label' => __('Replace new line to <br'), 'params' => []],
            'plus'           => ['label' => __('Plus'), 'params' => [['label' => __('Plus'), 'defVal' => '']]],
            'prepend'        => ['label' => __('Prepend'), 'params' => [['label' => __('Prepend'), 'defVal' => '']]],
            'remove'         => ['label' => __('Remove'), 'params' => [['label' => __('Remove'), 'defVal' => '']]],
            'replace'        => [
                'label'  => __('Replace'),
                'params' => [
                    ['label' => __('Search'), 'defVal' => ''],
                    ['label' => __('Replace'), 'defVal' => '']
                ]
            ],
            'replace_first'  => [
                'label'  => __('Replace First'),
                'params' => [
                    ['label' => __('Search'), 'defVal' => ''],
                    ['label' => __('Replace'), 'defVal' => '']
                ]
            ],
            'reverse'        => ['label' => __('Reverse Array'), 'params' => []],
            'round'          => ['label' => __('Round'), 'params' => []],
            'rstrip'         => ['label' => __('Right Trim'), 'params' => []],
            'size'           => ['label' => __('Array Size'), 'params' => []],
            'slice'          => [
                'label'  => __('Slice'),
                'params' => [
                    ['label' => __('From'), 'defVal' => ''],
                    ['label' => __('To'), 'defVal' => '']
                ]
            ],
            'sort'           => ['label' => __('Array Sort'), 'params' => []],
            'strip'          => ['label' => __('Trim Text'), 'params' => []],
            'strip_html'     => ['label' => __('Strip Html Tags'), 'params' => []],
            'strip_newlines' => ['label' => __('Strip New Line'), 'params' => []],
            'times'          => ['label' => __('Times'), 'params' => [['label' => __('Times'), 'defVal' => '']]],
            'truncate'       => [
                'label'  => __('Truncate'),
                'params' => [
                    ['label' => __('Count'), 'Chars' => ''],
                    ['label' => __('Append Last'), 'defVal' => '']
                ]
            ],
            'truncatewords'  => [
                'label'  => __('Truncate Words'),
                'params' => [
                    ['label' => __('Words'), 'defVal' => ''],
                    ['label' => __('Append Last'), 'defVal' => '']
                ]
            ],
            'strtolower'     => ['label' => __('Lowercase'), 'params' => []],
            'uniq'           => ['label' => __('Unique Id Of Array'), 'params' => []],
            'upcase'         => ['label' => __('Uppercase'), 'params' => []],
            'url_decode'     => ['label' => __('Decode Url'), 'params' => []],
            'url_encode'     => ['label' => __('Encode Url'), 'params' => []],
        ];

        $customFilter = [
            'count'               => ['label' => __('Count'), 'params' => []],
            'money_with_currency' => ['label' => __('Price'), 'params' => []],
            'image_url'           => ['label' => __('Item Image URL'), 'params' => []],
            'ifEmpty'             => [
                'label'  => __('If Empty'),
                'params' => [['label' => __('Default'), 'defVal' => '']]
            ],
        ];

        return array_merge($filters, $customFilter);
    }

    /**
     * @param $subject
     *
     * @return float
     */
    public function money_with_currency($subject)
    {
        return $this->priceCurrency->convertAndFormat($subject);
    }

    /**
     * @param $subject
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function image_url($subject)
    {
        $productClone    = $this->productRepository->getById(
            $subject,
            false,
            $this->_storeManager->getStore()->getId()
        );
        $store           = $this->_storeManager->getStore();
        $productImageUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productClone->getImage();

        return $productImageUrl;
    }

    /**
     * @param $subject
     *
     * @return int
     */
    public function count($subject)
    {
        return count($subject);
    }

    /**
     * @param $subject
     * @param $default
     *
     * @return mixed
     */
    public function ifEmpty($subject, $default)
    {
        if (!$subject) {
            $subject = $default;
        }

        return $subject;
    }

    /**
     * @return array
     */
    public function addTemplateVars()
    {
        $templateVars = [
            'email_address'      => $this->registration->getEmailAddress(),
            'create_account_url' => $this->registration->getCreateAccountUrl(),
            'form_action_url'    => $this->_urlBuilder->getUrl('newsletter/subscriber/new', ['_secure' => true])
        ];

        return $templateVars;
    }
}
