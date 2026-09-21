<?php
/*
 * TASK-5XQXZK (DEC-TASK5XQXZK-001) — Mageplaza TableRate CSV importer with City/Area support.
 * TASK-JZXM66 — region/city consistency validation + template column list (`city_name` is
 * tolerated as an extra informational column and stripped before persistence).
 *
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 */

declare(strict_types=1);

namespace Launchpad\MageplazaTableRate\Model;

use Launchpad\MageplazaTableRate\Model\Adminhtml\SettingsPersister;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Registry;
use Mageplaza\TableRateShipping\Model\Import as MageplazaImport;

/**
 * Preference subclass of the Mageplaza importer (vendor source untouched — directive §17):
 *
 *  - accepts the SUPerset column list = Mageplaza's native list + `postcode`, `postcode_from`,
 *    `postcode_to`, `shipping_group` (fixing Mageplaza's own export→import round-trip gap) +
 *    the optional `city_code`;
 *  - validates every non-empty `city_code` against the live address hierarchy BEFORE persisting
 *    the row — an unknown code fails the row clearly and never silently creates an address unit;
 *  - inserts rows with Mageplaza's own Rate model (same table, same business semantics) and
 *    persists the city constraint atomically per row via the shared persister.
 *
 * Rows without `city_code` behave exactly like native Mageplaza imports (append-only).
 */
class MptablerateImport extends MageplazaImport
{
    private const COL_POSTCODE_FROM = 'postcode_from';
    private const COL_POSTCODE_TO = 'postcode_to';
    private const COL_SHIPPING_GROUP = 'shipping_group';
    private const COL_CITY_CODE = 'city_code';
    private const COL_CITY_NAME = 'city_name';

    /**
     * TASK-JZXM66 — single source of truth for the import/export column superset. The import
     * template (Model\Adminhtml\ImportTemplateBuilder) appends `city_name` to THIS list, so
     * template and importer can never drift apart.
     */
    private const TEMPLATE_COLUMNS = [
        self::COL_NAME,
        self::COL_COUNTRY_ID,
        self::COL_REGION,
        self::COL_WEIGHT_FROM,
        self::COL_WEIGHT_TO,
        self::COL_SUBTOTAL_FROM,
        self::COL_SUBTOTAL_TO,
        self::COL_QTY_FROM,
        self::COL_QTY_TO,
        self::COL_PRODUCT_FIXED_RATE,
        self::COL_PRODUCT_PERCENTAGE_RATE,
        self::COL_WEIGHT_FIXED_RATE,
        self::COL_ORDER_FIXED_RATE,
        self::COL_DELIVERY,
        'postcode',
        self::COL_POSTCODE_FROM,
        self::COL_POSTCODE_TO,
        self::COL_SHIPPING_GROUP,
        self::COL_CITY_CODE,
    ];

    /**
     * Superset of the native required columns (re-declared — we widen the native list, never
     * shrink it: every native column stays REQUIRED so legacy files keep importing).
     *
     * @var array
     */
    protected $_columnNames = self::TEMPLATE_COLUMNS;

    /** @var \Magento\ImportExport\Model\ResourceModel\Import\Data */
    private \Magento\ImportExport\Model\ResourceModel\Import\Data $importDataProp;

    /** @var \Mageplaza\TableRateShipping\Model\RateFactory */
    private \Mageplaza\TableRateShipping\Model\RateFactory $rateFactoryProp;

    /** @var \Magento\Directory\Model\RegionFactory */
    private \Magento\Directory\Model\RegionFactory $regionFactoryProp;

    /** @var \Mageplaza\TableRateShipping\Helper\Data */
    private \Mageplaza\TableRateShipping\Helper\Data $helperProp;

    /** @var SettingsPersister */
    private SettingsPersister $settingsPersister;

    public function __construct(
        Context $context,
        Registry $registry,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\ImportExport\Model\Import $importModel,
        \Mageplaza\TableRateShipping\Model\RateFactory $rateFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Mageplaza\TableRateShipping\Helper\Data $helper,
        SettingsPersister $settingsPersister,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->importDataProp = $importData;
        $this->rateFactoryProp = $rateFactory;
        $this->regionFactoryProp = $regionFactory;
        $this->helperProp = $helper;
        $this->settingsPersister = $settingsPersister;
        parent::__construct(
            $context,
            $registry,
            $importData,
            $importModel,
            $rateFactory,
            $regionFactory,
            $helper,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Re-implements the parent append-only import loop with shipping_group/postcode columns
     * (the parent's row normalizer is private) plus the City/Area constraint.
     *
     * @param array $bunchData
     * @param mixed $resultBlock
     * @param int $methodId
     * @return array
     */
    public function processImport($bunchData, $resultBlock, $methodId)
    {
        $importDataSource = $this->importDataProp->getDataSourceModel();
        $importDataSource->cleanBunches();
        $importDataSource->saveBunch('mp_table_rate', \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND, $bunchData);

        $success = 0;
        $error = 0;

        while ($rates = $this->importDataProp->getNextBunch()) {
            foreach ($rates as $rate) {
                try {
                    $cityCode = $this->normalizeRateRow($rate);

                    // Unknown City/Area codes fail the row LOUDLY before anything is persisted.
                    if ($cityCode !== '' && !$this->settingsProviderCityCodeExists($cityCode)) {
                        $resultBlock->addError(
                            __(
                                'Row "%1": City / Area code "%2" does not exist in the address hierarchy.',
                                [$rate['name'] ?? '', $cityCode]
                            )
                        );
                        $error++;

                        continue;
                    }

                    // TASK-JZXM66 — a coded city/area must live under the row's own region.
                    $regionMismatch = $this->validateCityRegion(
                        $cityCode,
                        (string) ($rate['region'] ?? ''),
                        (string) ($rate['name'] ?? '')
                    );
                    if ($regionMismatch !== null) {
                        $resultBlock->addError($regionMismatch);
                        $error++;

                        continue;
                    }

                    $savedRate = $this->rateFactoryProp->create();
                    $savedRate->setData($rate);
                    $savedRate->save();
                    $this->settingsPersister->saveRateCity((int) $savedRate->getId(), $cityCode);
                    $success++;
                } catch (\Exception $e) {
                    $resultBlock->addError($e->getMessage());
                    $error++;
                }
            }
        }

        return compact('success', 'error');
    }

    /**
     * TASK-JZXM66 — import template columns: the importer's own superset + the trailing
     * informational `city_name` column. The admin template is generated from THIS list, so
     * template and importer stay in sync by construction. Extra columns are tolerated by the
     * Mageplaza header validation (array_diff on required only) and `city_name` is stripped
     * during row normalization — it never reaches persistence.
     *
     * @return array<int, string>
     */
    public static function templateColumns(): array
    {
        return array_merge(self::TEMPLATE_COLUMNS, [self::COL_CITY_NAME]);
    }

    /**
     * TASK-JZXM66 — region/city consistency: when the (normalized) region is a concrete
     * region_id and a City/Area code is present, the code must belong to that region.
     * Wildcard regions (`*`), non-numeric regions and wildcard cities are legacy-compatible
     * and never checked here.
     *
     * @param string $cityCode validated, non-empty city/area code ('' = wildcard, skip)
     * @param string $region   region_id (numeric) | region code | '*' after normalization
     * @param string $rowLabel row display name for the error message
     * @return \Magento\Framework\Phrase|null null = consistent (or nothing to check)
     */
    public function validateCityRegion(string $cityCode, string $region, string $rowLabel = ''): ?\Magento\Framework\Phrase
    {
        if ($cityCode === '' || !ctype_digit($region) || (int) $region <= 0) {
            return null;
        }

        if ($this->settingsPersister->cityRegionId($cityCode) === (int) $region) {
            return null;
        }

        if ($rowLabel === '') {
            return __('City / Area code "%1" does not belong to region "%2".', [$cityCode, $region]);
        }

        return __('Row "%1": City / Area code "%2" does not belong to region "%3".', [$rowLabel, $cityCode, $region]);
    }

    private function settingsProviderCityCodeExists(string $cityCode): bool
    {
        return $this->settingsPersister->cityCodeExists($cityCode);
    }

    /**
     * Row normalization (mirror of the parent's private processRateData: empty → `*`, region
     * code → region_id, postcode split) + shipping_group implode + city_code extraction
     * (removed from the row before the rate model is persisted). `city_name` (TASK-JZXM66,
     * informational template column) is stripped defensively — display labels never persist.
     *
     * @param array $rate (by reference — cleaned of the city_code/city_name keys)
     * @return string the validated city code ('' = wildcard)
     */
    private function normalizeRateRow(array &$rate): string
    {
        if (empty($rate['region'])) {
            $rate['region'] = '*';
        }
        if (empty($rate['country_id'])) {
            $rate['country_id'] = '*';
        }

        $region = $this->regionFactoryProp->create()->loadByCode(
            (string) $rate['region'],
            (string) $rate['country_id']
        );
        if ($regionId = $region->getId()) {
            $rate['region'] = $regionId;
        }

        if (isset($rate[self::COL_SHIPPING_GROUP]) && is_array($rate[self::COL_SHIPPING_GROUP])) {
            $rate[self::COL_SHIPPING_GROUP] = implode(',', $rate[self::COL_SHIPPING_GROUP]);
        }

        $rate['postcode_range'] = false;
        foreach ([self::COL_POSTCODE_FROM, self::COL_POSTCODE_TO] as $key) {
            if (empty($rate[$key])) {
                $rate[$key] = '';

                continue;
            }
            $postcode = $this->helperProp->getPostcodeData($rate[$key]);
            $rate[$key . '_alpha'] = $postcode['alpha'];
            $rate[$key . '_num'] = $postcode['num'];
            $rate['postcode_range'] = true;
        }

        $cityCode = trim((string) ($rate[self::COL_CITY_CODE] ?? ''));
        unset($rate[self::COL_CITY_CODE]);
        unset($rate[self::COL_CITY_NAME]);

        return $cityCode;
    }
}
