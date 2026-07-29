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

namespace Mirasvit\Core\Plugin\Backend;

use Magento\Config\Model\Config\ScopeDefiner;
use Magento\Config\Model\Config\Structure;
use Mirasvit\Core\Block\Adminhtml\Config\ExtensionInfo;
use Mirasvit\Core\Service\CompatibilityService;

/**
 * @see \Magento\Config\Model\Config\Structure::getElementByPathParts()
 */
class AddExtensionInfoBlockPlugin
{
    private $scopeDefiner;

    public function __construct(
        ScopeDefiner $scopeDefiner
    ) {
        $this->scopeDefiner = $scopeDefiner;
    }

    /**
     * @param Structure                                              $subject
     * @param \Magento\Config\Model\Config\Structure\Element\Section $result
     *
     * @return Structure\Element\Section
     */
    public function afterGetElementByPathParts(Structure $subject, $result)
    {
        if (CompatibilityService::isMarketplace()) {
            return $result;
        }

        //check if enabled
        $sectionData = $result->getData();

        if (!isset($sectionData['tab']) || $sectionData['tab'] !== 'mirasvit') {
            return $result;
        }

        [$moduleName] = explode('::', $sectionData['resource']);

        if (!$moduleName) {
            return $result;
        }

        $sectionData['children'] = [
                'suggester' => [
                    'id'             => 'mirasvit_extension_info',
                    'type'           => 'text',
                    'sortOrder'      => '1',
                    'showInDefault'  => '1',
                    'showInWebsite'  => '1',
                    'showInStore'    => '1',
                    'label'          => __('Extension Information'),
                    'frontend_model' => ExtensionInfo::class,
                    'module_name'    => $moduleName,
                ],
            ] + $sectionData['children'];

        $result->setData($sectionData, $this->scopeDefiner->getScope());

        return $result;
    }
}
