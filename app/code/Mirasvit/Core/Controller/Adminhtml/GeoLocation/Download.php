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

namespace Mirasvit\Core\Controller\Adminhtml\GeoLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Mirasvit\Core\Block\Adminhtml\Config\GeoLocationField;
use Mirasvit\Core\Service\GeoLocationService;

class Download extends Action
{
    const ADMIN_RESOURCE = 'Mirasvit_Core::core_settings';

    private $geoLocationService;

    private $jsonFactory;

    private $geoLocationField;

    public function __construct(
        Context            $context,
        GeoLocationService $geoLocationService,
        JsonFactory        $jsonFactory,
        GeoLocationField   $geoLocationField
    ) {
        $this->geoLocationService = $geoLocationService;
        $this->jsonFactory        = $jsonFactory;
        $this->geoLocationField   = $geoLocationField;

        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $isAjax = $this->getRequest()->isAjax();

        try {
            $result = $this->geoLocationService->downloadDatabase();

            if ($isAjax) {
                return $this->createJsonResponse($result);
            }

            if ($result['downloaded']) {
                $this->messageManager->addSuccessMessage(__($result['message']));
            } else {
                $this->messageManager->addNoticeMessage(__($result['message']));
            }
        } catch (\Exception $e) {
            if ($isAjax) {
                return $this->createJsonResponse([
                    'downloaded' => false,
                    'message'    => __('Error downloading database: %1', $e->getMessage()),
                ], false);
            }

            $this->messageManager->addErrorMessage(__('Error downloading database: %1', $e->getMessage()));
        }

        return $this->resultRedirectFactory->create()
            ->setPath('adminhtml/system_config/edit', ['section' => 'mst_core']);
    }

    private function createJsonResponse(array $result, bool $success = true): ResultInterface
    {
        $info = $this->geoLocationService->getDatabaseInfo();

        $response = [
            'success'    => $success && !isset($result['error']),
            'downloaded' => $result['downloaded'] ?? false,
            'message'    => (string)($result['message'] ?? ''),
        ];

        if ($info) {
            $response['info'] = [
                'version' => $this->geoLocationField->formatVersion($info['version'] ?? null),
                'size'    => number_format((float)$info['size'] / 1024 / 1024, 1) . ' MB',
            ];
        }

        return $this->jsonFactory->create()->setData($response);
    }
}
