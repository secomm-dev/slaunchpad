<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\SeoAutolink\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Framework\Module\Manager as ModuleManager;

class Target implements ArrayInterface
{
    const CMS_PAGE = 1;
    const CATEGORY_DESCRIPTION = 2;
    const PRODUCT_SHORT_DESCRIPTION = 3;
    const PRODUCT_FULL_DESCRIPTION = 4;
    const CMS_BLOCK = 5;
    const SEO_DESCRIPTION = 6;
    const MIRASVIT_BLOG_POST = 7;
    const PRODUCT_ATTRIBUTE = 8;
    const MAGEPLAZA_BLOG_POST = 9;

    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $options = [
            [
                'value' => self::CMS_PAGE,
                'label' => __('CMS page')
            ],
            [
                'value' => self::CMS_BLOCK,
                'label' => __('CMS block')
            ],
            [
                'value' => self::CATEGORY_DESCRIPTION,
                'label' => __('Category description')
            ],
            [
                'value' => self::PRODUCT_SHORT_DESCRIPTION,
                'label' => __('Product short description')
            ],
            [
                'value' => self::PRODUCT_FULL_DESCRIPTION,
                'label' => __('Product full description')
            ],
            [
                'value' => self::PRODUCT_ATTRIBUTE,
                'label' => __('Product attributes')
            ],
        ];

        if ($this->moduleManager->isEnabled('Mirasvit_Seo')) {
            $options[] = [
                'value' => self::SEO_DESCRIPTION,
                'label' => __('SEO description')
            ];
        }

        if ($this->moduleManager->isEnabled('Mirasvit_BlogMx')
            || $this->moduleManager->isEnabled('Mirasvit_Blog')
        ) {
            $options[] = [
                'value' => self::MIRASVIT_BLOG_POST,
                'label' => __('Blog MX post content')
            ];
        }

        if ($this->moduleManager->isEnabled('Mageplaza_Blog')) {
            $options[] = [
                'value' => self::MAGEPLAZA_BLOG_POST,
                'label' => __('Mageplaza Blog post content')
            ];
        }

        return $options;
    }
}
