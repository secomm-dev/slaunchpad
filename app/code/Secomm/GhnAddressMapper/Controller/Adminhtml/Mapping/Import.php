<?php declare(strict_types=1);

namespace Secomm\GhnAddressMapper\Controller\Adminhtml\Mapping;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Secomm\GhnAddressMapper\Model\Import\MappingImporter;
use Psr\Log\LoggerInterface;

class Import extends Action
{
    const ADMIN_RESOURCE = 'Secomm_GhnAddressMapper::mapping';

    public function __construct(
        Context $context,
        protected JsonFactory $resultJsonFactory,
        protected Filesystem $filesystem,
        protected Csv $csv,
        protected MappingImporter $mappingImporter,
        protected LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            $files = $this->getRequest()->getFiles();
            if (!isset($files['import_file']) || !$files['import_file']['tmp_name']) {
                throw new LocalizedException(__('No file uploaded.'));
            }

            $fileName = $files['import_file']['name'] ?? '';
            $allowedExtensions = ['csv'];
            $extension = strtolower(pathinfo((string)$fileName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions)) {
                throw new LocalizedException(__('Only CSV files are allowed. Uploaded file extension: %1', $extension));
            }

            $updateExisting = (bool)$this->getRequest()->getParam('update_existing', false);
            $filePath = $files['import_file']['tmp_name'];
            $csvData = $this->csv->getData($filePath);

            if (count($csvData) < 2) {
                throw new LocalizedException(__('CSV file is empty or missing headers.'));
            }

            $result = $this->mappingImporter->importAll($csvData, $updateExisting);
        } catch (LocalizedException $e) {
            $this->logger->warning('GHN Address Mapper: Import validation error', ['exception' => $e]);
            $result['errors'][] = $e->getMessage();
        } catch (\Exception $e) {
            $this->logger->error('GHN Address Mapper: Import unexpected error', ['exception' => $e]);
            $result['errors'][] = __('An error occurred during import. Please check the log for details.');
        }

        if ($this->getRequest()->isXmlHttpRequest()) {
            return $this->resultJsonFactory->create()->setData($result);
        }

        if ($result['imported'] > 0 || $result['updated'] > 0 || $result['skipped'] > 0) {
            $this->messageManager->addSuccessMessage(
                __('Import Completed: %1 imported, %2 updated, %3 skipped, %4 failed.',
                    $result['imported'], $result['updated'], $result['skipped'], $result['failed'])
            );
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $this->messageManager->addErrorMessage((string)$err);
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('*/*/index');
    }
}
