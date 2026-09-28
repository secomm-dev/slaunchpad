<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\AddressDropdown\Model;

use Magento\Framework\ObjectManagerInterface;

/**
 * Standard model factory for {@see Region}.
 *
 * TASK-SEC-BLOCKER: restored after the "refactor AddressDropdown…" commit removed the model
 * factories while Helper\Data, the save/delete commands and test suites still inject them.
 */
class RegionFactory
{
    private ObjectManagerInterface $objectManager;

    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @param array $data
     * @return Region
     */
    public function create(array $data = []): Region
    {
        return $this->objectManager->create(Region::class, $data);
    }
}
