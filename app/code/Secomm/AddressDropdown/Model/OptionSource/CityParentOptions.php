<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\AddressDropdown\Model\OptionSource;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Secomm\AddressDropdown\Api\Data\CityInterface;
use Secomm\AddressDropdown\Api\HierarchyAddressImportInterface;
use Secomm\AddressDropdown\Model\ResourceModel\CityModel\CityCollectionFactory;

/**
 * TASK-9EX975 Slice B — flat parent-city picker for the admin city form (TL UX decision:
 * no tree UI). Lists the WHOLE subtree of the region the city belongs to, one option per
 * city, indented with "— " repeated per level ("— Trực tiếp dưới Region —" is the empty
 * caption). Self + descendants are excluded so the picker can never create a cycle.
 */
class CityParentOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CityCollectionFactory $cityCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int|string, label: string, __disableTmpl: bool}>
     */
    public function toOptionArray(): array
    {
        $regionId = $this->resolveRegionId();
        if (!$regionId) {
            return [];
        }
        $selfId = (int)($this->request->getParam(CityInterface::CITY_ID) ?: 0);

        $rows = [];
        foreach ($this->cityCollectionFactory->create()
                     ->addFieldToFilter(CityInterface::REGION_ID, $regionId) as $row) {
            $rows[(int)$row->getCityId()] = [
                'parent' => $row->getParentCityId() !== null ? (int)$row->getParentCityId() : null,
                'label' => (string)$row->getDefaultName(),
            ];
        }
        if ($selfId && !isset($rows[$selfId])) {
            $selfId = 0;
        }

        $excluded = $this->selfAndDescendants($rows, $selfId);
        $options = [];
        foreach ($rows as $cityId => $row) {
            if (isset($excluded[$cityId])) {
                continue;
            }
            $depth = $this->depthOf($cityId, $rows);
            $options[] = [
                'value' => $cityId,
                'label' => str_repeat('— ', max(0, $depth - 1)) . $row['label'],
                '__disableTmpl' => true,
            ];
        }

        return $options;
    }

    /**
     * Region of the form: explicit region_id param (add flow, incl. Add-child), otherwise
     * the region of the city being edited.
     */
    private function resolveRegionId(): ?int
    {
        if ($regionId = $this->request->getParam(CityInterface::REGION_ID)) {
            return (int)$regionId;
        }
        $cityId = (int)($this->request->getParam(CityInterface::CITY_ID) ?: 0);
        if (!$cityId) {
            return null;
        }
        $city = $this->cityCollectionFactory->create()
            ->addFieldToFilter(CityInterface::CITY_ID, $cityId)
            ->getFirstItem();

        return $city->isEmpty() ? null : (int)$city->getRegionId();
    }

    /**
     * @param array<int, array{parent: int|null, label: string}> $rows
     * @return array<int, true>
     */
    private function selfAndDescendants(array $rows, int $selfId): array
    {
        if (!$selfId) {
            return [];
        }
        $children = [];
        foreach ($rows as $cityId => $row) {
            if ($row['parent'] !== null) {
                $children[(int)$row['parent']][] = $cityId;
            }
        }

        $excluded = [$selfId => true];
        $queue = [$selfId];
        while ($queue) {
            $current = array_shift($queue);
            foreach ($children[$current] ?? [] as $childId) {
                if (!isset($excluded[$childId])) {
                    $excluded[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return $excluded;
    }

    /**
     * 1-based depth below the region; guards cycles + bound (defensive — rows come from DB).
     *
     * @param array<int, array{parent: int|null, label: string}> $rows
     */
    private function depthOf(int $cityId, array $rows): int
    {
        $depth = 1;
        $visited = [$cityId => true];
        $parent = $rows[$cityId]['parent'] ?? null;

        while ($parent !== null && isset($rows[$parent])) {
            if (isset($visited[$parent]) || count($visited) > HierarchyAddressImportInterface::MAX_DEPTH) {
                break; // cycle / bound — return partial depth rather than looping
            }
            $visited[$parent] = true;
            $depth++;
            $parent = $rows[$parent]['parent'];
        }

        return $depth;
    }
}
