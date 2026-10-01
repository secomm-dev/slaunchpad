<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Secomm\CodRisk\Model\Config;

/**
 * Save-time guard for the Block Threshold field: must be > Warning Threshold
 * (spec nguồn §9.3, AC-011).
 *
 * Scope note: compares against the currently stored warning value at the same
 * scope. When both thresholds are changed in one save, run the save twice (the
 * second pass validates the persisted pairing) — the runtime rules additionally
 * treat (warning, warning] as WARNING so an inconsistent pairing can never
 * produce an unexpected BLOCK.
 */
class BlockThreshold extends Value
{
    /**
     * Parent App\Config\Value does not expose the ScopeConfigInterface it receives —
     * keep our own reference for the warning-threshold lookup.
     */
    private ScopeConfigInterface $scopeConfig;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->scopeConfig = $config;
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSave(): Value
    {
        parent::beforeSave();

        $block = (int)$this->getValue();
        $warning = $this->getStoredWarning();

        if ($block < 1) {
            throw new LocalizedException(new Phrase('Block Threshold must be greater than 0.'));
        }
        if ($warning !== null && $block <= $warning) {
            throw new LocalizedException(
                new Phrase('Block Threshold must be greater than Warning Threshold (current: %1).', $warning)
            );
        }

        return $this;
    }

    private function getStoredWarning(): ?int
    {
        // Value objects carry scope as raw strings ('default'/'websites'/'stores') —
        // identical to the ScopeInterface constant values.
        $scopeType = (string)$this->getScope();
        $scopeCode = (string)$this->getScopeCode();

        $value = $this->scopeConfig->getValue(
            Config::XML_PATH_WARNING_THRESHOLD,
            $scopeType !== '' ? $scopeType : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $scopeCode !== '' ? $scopeCode : null
        );

        return $value === null ? null : (int)$value;
    }
}
