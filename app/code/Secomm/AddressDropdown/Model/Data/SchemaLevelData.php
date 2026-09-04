<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;

class SchemaLevelData extends DataObject implements SchemaLevelInterface
{
    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return (string)$this->getData(self::ENTITY_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setEntityType(string $entityType): void
    {
        $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * @inheritDoc
     */
    public function getDepth(): int
    {
        return (int)$this->getData(self::DEPTH);
    }

    /**
     * @inheritDoc
     */
    public function setDepth(int $depth): void
    {
        $this->setData(self::DEPTH, $depth);
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string)$this->getData(self::LABEL);
    }

    /**
     * @inheritDoc
     */
    public function setLabel(string $label): void
    {
        $this->setData(self::LABEL, $label);
    }

    /**
     * @inheritDoc
     */
    public function getPlaceholder(): ?string
    {
        $placeholder = $this->getData(self::PLACEHOLDER);

        return $placeholder === null ? null : (string)$placeholder;
    }

    /**
     * @inheritDoc
     */
    public function setPlaceholder(?string $placeholder): void
    {
        $this->setData(self::PLACEHOLDER, $placeholder);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    /**
     * @inheritDoc
     */
    public function setSortOrder(int $sortOrder): void
    {
        $this->setData(self::SORT_ORDER, $sortOrder);
    }

    /**
     * @inheritDoc
     */
    public function isRequired(): bool
    {
        return (bool)$this->getData(self::REQUIRED);
    }

    /**
     * @inheritDoc
     */
    public function setRequired(bool $required): void
    {
        $this->setData(self::REQUIRED, $required);
    }
}
