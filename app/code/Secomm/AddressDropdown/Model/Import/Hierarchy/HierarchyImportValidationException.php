<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Import\Hierarchy;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * FEAT-2PZQKJ / TASK-ADT94K — thrown by HierarchyImportService::import() when validation fails.
 * Carries the full result so callers can report every row-level error (STOP_ON_ERROR semantics:
 * nothing is written when any row is invalid).
 */
class HierarchyImportValidationException extends LocalizedException
{
    private HierarchyImportResult $result;

    public function __construct(Phrase $phrase, HierarchyImportResult $result)
    {
        parent::__construct($phrase);
        $this->result = $result;
    }

    public function getResult(): HierarchyImportResult
    {
        return $this->result;
    }
}
