<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\VietNamAddress\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Secomm\VietNamAddress\Model\Scheme\VnSchemeRegistry;
use Secomm\VietNamAddress\Model\Scheme\VnSchemes;

/**
 * DEC-FEATYA2C0W-003 — guards manual admin flips of secomm_vietnam_address/general/active_scheme.
 *
 * The value may only change to a scheme whose data is actually installed (registry status
 * CURRENT). The importer writes the config through ConfigResource, which bypasses backend
 * models, so the import flow is unaffected by this guard.
 */
class ActiveScheme extends Value
{
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        private readonly VnSchemeRegistry $schemeRegistry,
        ?\Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        ?\Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Reject a value whose scheme is not installed (registry CURRENT) and not already active.
     *
     * @return DataObject
     * @throws LocalizedException
     */
    public function beforeSave(): DataObject
    {
        $value = (string)$this->getValue();
        VnSchemes::assertKnown($value);

        $current = $this->schemeRegistry->getCurrent();
        if ($current !== null && $current !== $value) {
            throw new LocalizedException(
                __(
                    'Scheme "%1" is not installed (active: "%2"). Run '
                    . '`bin/magento secomm:vietnam-address:import --scheme %1 --swap` first.',
                    $value,
                    $current
                )
            );
        }

        return parent::beforeSave();
    }
}
