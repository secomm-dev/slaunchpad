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

/**
 * Renders effective_from / effective_to as pure dates (Y-m-d).
 *
 * The core Date column parses the stored value as UTC and shifts it into the
 * store timezone (+7), turning a date-only record "2026-10-01" into
 * "2026-10-01 07:00:00" (bug report 02/10). The DB columns are xsi:type="date"
 * — no timezone math belongs here; display the stored date verbatim.
 */
class EffectiveDate extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
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
            $value = (string)($row[$this->getName()] ?? '');
            $row[$this->getName()] = $value === '' ? '' : substr($value, 0, 10);
        }

        return $dataSource;
    }
}
