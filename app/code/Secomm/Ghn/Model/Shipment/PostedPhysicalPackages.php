<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\Ghn\Model\Shipment;

use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * TASK-W5BW4F — the confirmed package rows from the admin package-information section, read
 * off the current request. Shared by BOTH observers so the pre-save gate and the post-commit
 * create always see the same source: the ONLY authoritative physical source on a fresh save
 * (the retry path reads the persisted snapshot instead).
 */
final class PostedPhysicalPackages
{
    /**
     * @return array|null null when this request carries no package information
     */
    public static function fromRequest(HttpRequest $request): ?array
    {
        if (!$request->isPost()) {
            return null;
        }

        $packages = $request->getParam('shipment')['physical_packages'] ?? null;

        return is_array($packages) && $packages !== [] ? $packages : null;
    }
}
