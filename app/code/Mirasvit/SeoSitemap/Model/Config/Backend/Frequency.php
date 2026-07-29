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


declare(strict_types=1);

namespace Mirasvit\SeoSitemap\Model\Config\Backend;

use Magento\Cron\Model\Config\Backend\Sitemap;

class Frequency extends Sitemap
{
    public function afterSave(): self
    {
        $groups = $this->getData('groups');

        if (isset($groups['xml']['groups']['generate']['fields']['time']['value'])) {
            $groups['generate']['fields']['time']['value'] = $groups['xml']['groups']['generate']['fields']['time']['value'];
            $this->setData('groups', $groups);
        }

        return parent::afterSave();
    }
}
