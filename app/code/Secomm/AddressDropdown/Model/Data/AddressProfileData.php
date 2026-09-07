<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Data;

use Magento\Framework\DataObject;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\Data\SchemaLevelInterface;

class AddressProfileData extends DataObject implements AddressProfileInterface
{
    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return (string)$this->getData(self::CODE);
    }

    /**
     * @inheritDoc
     */
    public function setCode(string $code): void
    {
        $this->setData(self::CODE, $code);
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): ?string
    {
        $label = $this->getData(self::LABEL);

        return $label === null ? null : (string)$label;
    }

    /**
     * @inheritDoc
     */
    public function setLabel(?string $label): void
    {
        $this->setData(self::LABEL, $label);
    }

    /**
     * @inheritDoc
     */
    public function getCountryId(): ?string
    {
        $countryId = $this->getData(self::COUNTRY_ID);

        return $countryId === null ? null : (string)$countryId;
    }

    /**
     * @inheritDoc
     */
    public function setCountryId(?string $countryId): void
    {
        $this->setData(self::COUNTRY_ID, $countryId);
    }

    /**
     * @inheritDoc
     */
    public function getLevels(): array
    {
        return (array)$this->getData(self::LEVELS);
    }

    /**
     * @inheritDoc
     */
    public function setLevels(array $levels): void
    {
        $this->setData(self::LEVELS, $levels);
    }
}
