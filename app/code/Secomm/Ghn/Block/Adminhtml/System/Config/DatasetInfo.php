<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Secomm\Ghn\Model\Address\Dataset\DatasetPaths;
use Secomm\Ghn\Model\Address\Dataset\Manifest;

/**
 * SPEC-TASK-TBM30R §10 — read-only "installed bundled dataset" info in admin config (directive
 * §13 "see" capability; all trigger operations stay CLI-only this phase).
 */
class DatasetInfo extends Field
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private readonly DatasetPaths $datasetPaths,
        private readonly Manifest $manifest,
        private readonly Json $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        try {
            $manifest = $this->manifest->read($this->datasetPaths->manifestFile($this->datasetPaths->bundledDir()));
        } catch (LocalizedException) {
            $manifest = null;
        }

        if ($manifest === null) {
            return (string) __('Bundled dataset: no manifest found — run secomm:ghn:address:export and commit the dataset.');
        }

        $counts = [];
        foreach ((array) ($manifest['files'] ?? []) as $relative => $entry) {
            $counts[] = sprintf('%s: %d', (string) $relative, (int) ($entry['record_count'] ?? 0));
        }

        return (string) __(
            'Bundled dataset version %1 (generated %2) — %3. Import: bin/magento secomm:ghn:address:import · Audit: secomm:ghn:address:audit',
            (string) ($manifest['dataset_version'] ?? '?'),
            (string) ($manifest['generated_at'] ?? '?'),
            $this->serializer->serialize($counts)
        );
    }
}
