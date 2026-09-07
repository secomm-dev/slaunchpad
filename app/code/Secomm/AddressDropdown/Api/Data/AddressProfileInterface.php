<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — an Address Profile: how a country/address context renders and
 * traverses the generic hierarchy. Declared in etc/address_profiles.xml (DEC-FEAT2PZQKJ-001 D4).
 */
interface AddressProfileInterface
{
    public const CODE = 'code';
    public const LABEL = 'label';
    public const COUNTRY_ID = 'country_id';
    public const LEVELS = 'levels';

    /**
     * Unique profile code (XML <profile code="...">). Bound to a country via config
     * `address/profiles/mapping`.
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * @param string $code
     * @return void
     */
    public function setCode(string $code): void;

    /**
     * Translation key (NOT a pre-translated string — rendered through the declaring module's i18n).
     *
     * @return string|null
     */
    public function getLabel(): ?string;

    /**
     * @param string|null $label
     * @return void
     */
    public function setLabel(?string $label): void;

    /**
     * Informational country the profile was authored for (resolution itself is config-driven).
     *
     * @return string|null
     */
    public function getCountryId(): ?string;

    /**
     * @param string|null $countryId
     * @return void
     */
    public function setCountryId(?string $countryId): void;

    /**
     * Schema levels as declared (unsorted; use AddressSchemaProviderInterface for render order).
     *
     * @return \Secomm\AddressDropdown\Api\Data\SchemaLevelInterface[]
     */
    public function getLevels(): array;

    /**
     * @param \Secomm\AddressDropdown\Api\Data\SchemaLevelInterface[] $levels
     * @return void
     */
    public function setLevels(array $levels): void;
}
