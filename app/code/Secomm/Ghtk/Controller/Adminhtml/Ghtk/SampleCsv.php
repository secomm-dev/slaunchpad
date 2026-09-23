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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Psr\Log\LoggerInterface;
use Secomm\Ghtk\Model\GhtkAddressOverrideImport\CsvReader;

/**
 * Streams the GHTK mapping sample CSV.
 *
 * The sample content is sourced from a module file (files/sample_address_map.csv)
 * so it can be maintained alongside the codebase. If the file is missing the
 * controller falls back to a header-only CSV so the download still works.
 */
class SampleCsv extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Secomm_Ghtk::manage_map';

    private const SAMPLE_FILE = 'files/sample_address_map.csv';
    private const DOWNLOAD_NAME = 'secomm_ghtk_address_map_sample.csv';

    public function __construct(
        Context $context,
        private ComponentRegistrarInterface $componentRegistrar,
        private LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $path = $this->resolveSamplePath();

        if ($path !== null && is_file($path) && is_readable($path)) {
            $body = (string) file_get_contents($path);
        } else {
            $this->logger->warning(
                'GHTK sample CSV file not found; serving header-only fallback.',
                ['expected_path' => $path]
            );
            $body = implode(',', CsvReader::HEADER) . "\n";
        }

        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $response->setHeader(
            'Content-Disposition',
            'attachment; filename="' . self::DOWNLOAD_NAME . '"',
            true
        );
        $response->setBody($body);

        return $response;
    }

    private function resolveSamplePath(): ?string
    {
        try {
            $moduleRoot = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Secomm_Ghtk');
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return $moduleRoot === null ? null : $moduleRoot . '/' . self::SAMPLE_FILE;
    }
}
