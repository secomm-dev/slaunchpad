<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Model;

use Magefan\FacebookPixelPlus\Api\SessionManagerInterface;
use Magento\Framework\Session\SessionManager as MagentoSessionManager;

/**
 * Abstract management model
 */
class SessionManager implements SessionManagerInterface
{
    /**
     * Push Facebook Pixel data into session storage
     *
     * @param MagentoSessionManager $session
     * @param array $data
     * @return void
     */
    public function push(MagentoSessionManager $session, array $data): void
    {
        if ($data) {
            $pixel = $session->getMfFbPixelData() ?: [];
            $pixel[] = $data;
            $session->setMfFbPixelData($pixel);
        }
    }

    /**
     * Retrieve and clear Facebook Pixel data from session storage
     *
     * @param MagentoSessionManager $session
     * @return array
     */
    public function get(MagentoSessionManager $session): array
    {
        $data = $session->getMfFbPixelData() ?: [];
        $session->setMfFbPixelData(null);
        return $data;
    }
}
