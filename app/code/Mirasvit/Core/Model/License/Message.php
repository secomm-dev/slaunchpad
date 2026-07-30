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



namespace Mirasvit\Core\Model\License;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Notification\MessageInterface;
use Mirasvit\Core\Model\LicenseFactory;

class Message implements MessageInterface
{
    private $fullModuleList;

    private $licenseFactory;

    private $url;

    private $issues;

    public function __construct(
        FullModuleList $fullModuleList,
        LicenseFactory $licenseFactory,
        UrlInterface   $url
    ) {
        $this->fullModuleList = $fullModuleList;
        $this->licenseFactory = $licenseFactory;
        $this->url            = $url;
    }

    public function isDisplayed()
    {
        $issues = $this->getIssues();

        return count($issues) > 0;
    }

    public function getIdentity()
    {
        return hash("sha256", __CLASS__);
    }

    public function getSeverity()
    {
        return self::SEVERITY_CRITICAL;
    }

    public function getText()
    {
        $issues = $this->getIssues();

        $html = implode('<hr style="height: 1px; border: 0; background: #d1d1d1;">', $issues);
        $html .= '<hr style="height: 1px; border: 0; background: #d1d1d1;">';

        $html .= '<a style="float: right" href="' . $this->url->getUrl('mstcore/lc/refresh') . '">Revalidate</a>';

        return $html;
    }

    public function getIssues()
    {
        if ($this->issues !== null) {
            return $this->issues;
        }

        $issues = [];
        foreach ($this->fullModuleList->getAll() as $moduleData) {
            if (substr($moduleData['name'], 0, strlen('Mirasvit_')) !== 'Mirasvit_') {
                continue;
            }

            $status = $this->licenseFactory->create()->getStatus($moduleData['name']);
            if ($status !== true) {
                $issues[] = $status;
            }
        }

        $this->issues = array_unique($issues);

        return $this->issues;
    }
}