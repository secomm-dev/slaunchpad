<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api;

use Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportResult;

/**
 * FEAT-2PZQKJ (core pulled forward into TASK-ADT94K) — hierarchy-aware address import slice.
 *
 * Upserts regions and recursive city nodes (directory_region_city incl. parent_city_id + code)
 * identified by CODE, never by display name. Complements the legacy 9-column ImportExport
 * entity (address_dropdown), which stays untouched.
 *
 * Row contract (assoc array, one per entity — country adapters build these from their datasets):
 *   entity_type  string  'region' | 'city'
 *   region_code  string  official region code (required for city rows; ignored for region rows)
 *   code         string  stable identifier of the row's own entity (unique per entity type)
 *   parent_code  string  '' for regions and depth-1 cities; parent city code for deeper nodes
 *   default_name string  non-localised display/fallback name
 *   names        array   map of locale => localised name, e.g. ['vi_VN' => 'An Biên']
 */
interface HierarchyAddressImportInterface
{
    public const ENTITY_TYPE_REGION = 'region';
    public const ENTITY_TYPE_CITY = 'city';

    /** Maximum hierarchy depth below a region (matches LocationHierarchyProvider walk guard). */
    public const MAX_DEPTH = 16;

    /** Shared code column limit (directory_region_city.code is VARCHAR(64)) — TASK-9EX975 Slice B promotes it for admin CRUD reuse. */
    public const CODE_COLUMN_LIMIT = 64;

    /**
     * Validate rows against the contract and current DB state without writing anything.
     */
    public function validate(string $countryId, array $rows): HierarchyImportResult;

    /**
     * Validate then upsert. Import order never affects the result: regions first, then city
     * nodes level by level with parents resolved by code. Throws on validation errors.
     *
     * @throws \Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportValidationException
     */
    public function import(string $countryId, array $rows): HierarchyImportResult;
}
