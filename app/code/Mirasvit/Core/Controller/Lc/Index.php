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



namespace Mirasvit\Core\Controller\Lc;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Mirasvit\Core\Model\LicenseFactory;
use Mirasvit\Core\Service\PackageService;

class Index extends Action
{
    private $packageService;

    private $licenseFactory;

    public function __construct(
        PackageService $packageService,
        LicenseFactory $licenseFactory,
        Context        $context
    ) {
        $this->packageService = $packageService;
        $this->licenseFactory = $licenseFactory;

        parent::__construct($context);
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD)
     */
    public function execute()
    {
        $toJson = $this->_request->getParam('json') ? true : false;

        if (!$toJson) {
            echo '<pre>';
        }

        $result = [];
        foreach ($this->packageService->getPackageList() as $package) {
            foreach ($package->getModuleList() as $moduleName) {
                $license = $this->licenseFactory->create();

                [, $name] = explode('_', $moduleName);
                $license->load('\\' . $name);

                $license->clear();

                $data = [
                    $moduleName,
                    $license->load('\\' . $name),
                    $license->getStatus('\\' . $name),
                    str_replace($package->getVersion(), '', $package->getVersionTxt()),
                ];

                $result[] = $data;

                if (!$toJson) {
                    foreach ($data as $v) {
                        echo $this->renderVal($v) . "\t";
                    }

                    echo PHP_EOL;
                }
            }
        }

        if ($toJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_PRETTY_PRINT);
        }
    }

    /**
     * @param string|bool $value
     *
     * @return string
     */
    private function renderVal($value)
    {
        if (is_bool($value)) {
            $value = $value ? 'T' : 'F';
        } else {
            $value = $value ? $value : '_';
        }
        $l = 20 - strlen($value);
        if ($l < 0) {
            $l = 0;
        }

        return '[ ' . $value . ' ]' . str_repeat(' ', $l);
    }
}
