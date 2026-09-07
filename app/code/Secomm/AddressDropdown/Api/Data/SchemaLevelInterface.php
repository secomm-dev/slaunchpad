<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Api\Data;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — one level of an Address Schema. Labels are translation keys from
 * the resolved profile; they are NEVER inferred from depth (DEC-FEAT2PZQKJ-001 Decision 2).
 */
interface SchemaLevelInterface
{
    public const ENTITY_TYPE = 'entity_type';
    public const DEPTH = 'depth';
    public const LABEL = 'label';
    public const PLACEHOLDER = 'placeholder';
    public const SORT_ORDER = 'sort_order';
    public const REQUIRED = 'required';

    public const ENTITY_TYPE_REGION = 'region';
    public const ENTITY_TYPE_CITY = 'city';

    /**
     * @return string entity_type — one of self::ENTITY_TYPE_*
     */
    public function getEntityType(): string;

    /**
     * @param string $entityType
     * @return void
     */
    public function setEntityType(string $entityType): void;

    /**
     * City depth counted from region (1 = directly below region); 0 for the region level.
     *
     * @return int
     */
    public function getDepth(): int;

    /**
     * @param int $depth
     * @return void
     */
    public function setDepth(int $depth): void;

    /**
     * Translation key for the field label.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     * @return void
     */
    public function setLabel(string $label): void;

    /**
     * @return string|null
     */
    public function getPlaceholder(): ?string;

    /**
     * @param string|null $placeholder
     * @return void
     */
    public function setPlaceholder(?string $placeholder): void;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     * @return void
     */
    public function setSortOrder(int $sortOrder): void;

    /**
     * @return bool
     */
    public function isRequired(): bool;

    /**
     * @param bool $required
     * @return void
     */
    public function setRequired(bool $required): void;
}
