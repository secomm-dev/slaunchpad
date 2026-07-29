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



namespace Mirasvit\Seo\Block\Adminhtml;

use Magento\Framework\DataObject;
use Magento\Backend\Block\Template\Context;
use Mirasvit\Core\Block\Adminhtml\AbstractMenu;
use Magento\Framework\Module\Manager ;

class Menu extends AbstractMenu
{
    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @param Context $context
     * @param Manager $moduleManager
     */
    public function __construct(
        Context $context,
        Manager $moduleManager
    ) {
        $this->visibleAt(['seo', 'seoautolink']);
        $this->moduleManager = $moduleManager;

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     */
    protected function buildMenu()
    {
        $this->addItem([
            'resource' => 'Mirasvit_Seo::seo_redirect',
            'title'    => __('Redirects'),
            'url'      => $this->urlBuilder->getUrl('seo/redirect'),
        ])->addItem([
            'resource' => 'Mirasvit_Seo::seo_canonicalRewrite',
            'title'    => __('Canonical rewrite'),
            'url'      => $this->urlBuilder->getUrl('seo/canonicalRewrite'),
        ]);
        ;

        if ($this->moduleManager->isEnabled('Mirasvit_SeoAutolink')) {
            $this->addItem([
                'resource' => 'Mirasvit_SeoAutolink::seoautolink_link',
                'title' => __('Cross-links'),
                'url' => $this->urlBuilder->getUrl('seoautolink/link'),
            ]);
        }

        if ($this->moduleManager->isEnabled('Mirasvit_SeoSitemap')) {
            $this->addItem([
                'resource' => 'Magento_Sitemap::sitemap',
                'title' => __('Sitemap'),
                'url' => $this->urlBuilder->getUrl('adminhtml/sitemap/'),
            ]);
        }

        $this->addItem([
            'resource' => 'Mirasvit_Seo::seo_settings',
            'title'    => __('Settings'),
            'url'      => $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/seo'),
        ]);

//        $this->addItem([
//            'resource' => 'Mirasvit_Seo::seo_checklist',
//            'title'    => __('SEO Checklist'),
//            'url'      => $this->urlBuilder->getUrl('seo/checklist'),
//        ]);

        $this->addSeparator();

        $this->addItem([
            'resource' => 'Mirasvit_Seo::seo_manual',
            'title'    => __('User manual'),
            'url'      => 'https://mirasvit.com/docs/module-seo/current/',
        ])->addItem([
            'resource' => 'Mirasvit_Seo::seo_get_support',
            'title'    => __('Get support'),
            'url'      => 'https://mirasvit.com/support/',
        ]);

        return $this;
    }
}
