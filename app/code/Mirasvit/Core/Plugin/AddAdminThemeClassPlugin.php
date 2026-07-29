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
 * @package   mirasvit/module-core
 * @version   1.7.15
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\Core\Plugin;

use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * @see \Magento\Backend\Model\View\Result\Page
 */
class AddAdminThemeClassPlugin
{
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function beforeRenderResult(Page $subject)
    {
        $adminTheme = $this->scopeConfig->getValue('admin/system_admin_design/active_theme');
        if ($adminTheme) {
            $themeClass = strtolower(str_replace('/', '-', $adminTheme));
            $subject->getConfig()->addBodyClass($themeClass);
        }

        return null;
    }
}
