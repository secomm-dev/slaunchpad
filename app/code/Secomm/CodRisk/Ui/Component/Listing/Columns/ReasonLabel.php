<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Secomm\CodRisk\Model\Reason\ReasonCatalog;

/**
 * Renders reason codes (reason / reason_code) as human labels from the
 * ReasonCatalog. A plain <column> shows the raw stored code in the cell —
 * its <options> only populate the filter dropdown (UX review 02/10).
 */
class ReasonLabel extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ReasonCatalog $reasonCatalog,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$row) {
            $code = (string)($row[$this->getName()] ?? '');
            $row[$this->getName()] = $code === ''
                ? ''
                : (string)__($this->reasonCatalog->getLabel($code));
        }

        return $dataSource;
    }
}