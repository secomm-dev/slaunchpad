<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghtk\Controller\Adminhtml\Ghtk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Secomm\Ghtk\Model\GhtkAddressMapImport\Summary;

/**
 * Handles the GHTK mapping CSV upload (replace-all).
 *
 * ACL via ADMIN_RESOURCE; form key validated automatically by the backend Action
 * for HttpPostActionInterface. Validates extension/size/upload-error, then delegates
 * to the transactional Importer.
 */
class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_Ghtk::manage_map';

    private const FIELD_NAME = 'mapping_csv';
    private const ALLOWED_EXTENSION = 'csv';
    private const MAX_SIZE_BYTES = 5242880; // 5 MB

    public function __construct(
        Context $context,
        private \Secomm\Ghtk\Model\GhtkAddressMapImport\Importer $importer,
        private Session $authSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('ghtk/ghtk/index');

        $file = $this->getRequest()->getFiles(self::FIELD_NAME);

        if (!is_array($file) || empty($file['tmp_name'])) {
            $this->messageManager->addErrorMessage(__('No file was uploaded.'));

            return $resultRedirect;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->messageManager->addErrorMessage(__('File upload failed (error code %1).', (int) $file['error']));

            return $resultRedirect;
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_SIZE_BYTES) {
            $this->messageManager->addErrorMessage(__('File is too large. Maximum size is 5 MB.'));

            return $resultRedirect;
        }

        $extension = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($extension !== self::ALLOWED_EXTENSION) {
            $this->messageManager->addErrorMessage(__('Invalid file type. Only .csv files are allowed.'));

            return $resultRedirect;
        }

        $adminUser = $this->authSession->getUser() !== null
            ? (string) $this->authSession->getUser()->getUsername()
            : '';
        $summary = $this->importer->import((string) $file['tmp_name'], $adminUser);

        $this->flashSummary($summary);

        return $resultRedirect;
    }

    private function flashSummary(Summary $summary): void
    {
        if ($summary->hasErrors()) {
            foreach ($summary->getErrors() as $error) {
                $this->messageManager->addErrorMessage($error);
            }
            $this->messageManager->addErrorMessage(
                __('Import aborted with %1 error(s). No changes were made.', $summary->getFailed())
            );

            return;
        }

        if (!$summary->isCommitted()) {
            $this->messageManager->addErrorMessage(__('Import did not complete. No changes were made.'));

            return;
        }

        $this->messageManager->addSuccessMessage(
            __(
                'Mapping replaced: %1 inserted, %2 updated, %3 removed, %4 skipped.',
                $summary->getInserted(),
                $summary->getUpdated(),
                $summary->getRemoved(),
                $summary->getSkipped()
            )
        );
    }
}
