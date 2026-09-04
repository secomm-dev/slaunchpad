<?php
declare(strict_types=1);
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\AddressDropdown\Model\Profile;

use Magento\Framework\Config\DataInterface;
use Secomm\AddressDropdown\Api\Data\AddressProfileInterface;
use Secomm\AddressDropdown\Api\NoSuchProfileException;
use Secomm\AddressDropdown\Model\Data\AddressProfileData;
use Secomm\AddressDropdown\Model\Data\SchemaLevelData;

/**
 * FEAT-2PZQKJ / TASK-NW66H9 — memoized pool of every profile declared across merged
 * etc/address_profiles.xml files, as AddressProfileInterface DTOs.
 */
class ProfilePool
{
    /**
     * @var AddressProfileInterface[]|null
     */
    private ?array $profiles = null;

    public function __construct(
        private readonly DataInterface $configData
    ) {
    }

    /**
     * All declared profiles keyed by code.
     *
     * @return AddressProfileInterface[]
     */
    public function getAll(): array
    {
        if ($this->profiles === null) {
            $this->profiles = [];
            foreach ((array)$this->configData->get('profiles', []) as $profileData) {
                $profile = new AddressProfileData();
                $profile->setCode((string)$profileData['code']);
                $profile->setLabel($profileData['label'] ?? null);
                $profile->setCountryId($profileData['country_id'] ?? null);

                $levels = [];
                foreach ((array)($profileData['levels'] ?? []) as $levelData) {
                    $level = new SchemaLevelData();
                    $level->setEntityType((string)$levelData['entity_type']);
                    $level->setDepth((int)$levelData['depth']);
                    $level->setLabel((string)$levelData['label']);
                    $level->setPlaceholder($levelData['placeholder'] ?? null);
                    $level->setSortOrder((int)$levelData['sort_order']);
                    $level->setRequired((bool)$levelData['required']);
                    $levels[] = $level;
                }
                $profile->setLevels($levels);

                $this->profiles[$profile->getCode()] = $profile;
            }
        }

        return $this->profiles;
    }

    /**
     * @throws NoSuchProfileException
     */
    public function getProfile(string $profileCode): AddressProfileInterface
    {
        $profiles = $this->getAll();
        if (!isset($profiles[$profileCode])) {
            throw new NoSuchProfileException(
                __('The address profile "%1" is not declared in any etc/address_profiles.xml file.', $profileCode)
            );
        }

        return $profiles[$profileCode];
    }

    public function hasProfile(string $profileCode): bool
    {
        return isset($this->getAll()[$profileCode]);
    }
}
