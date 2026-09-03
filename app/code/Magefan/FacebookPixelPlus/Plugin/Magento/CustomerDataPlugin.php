<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */

declare(strict_types=1);

namespace Magefan\FacebookPixelPlus\Plugin\Magento;

use Magefan\FacebookPixel\Model\Config;
use Magefan\FacebookPixelPlus\Api\SessionManagerInterface;
use Magento\Framework\Session\SessionManager as Session;
use Magento\Framework\App\RequestInterface;

class CustomerDataPlugin
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var SessionManagerInterface|mixed
     */
    protected $sessionManager;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @param RequestInterface $request
     * @param Session $session
     * @param SessionManagerInterface $sessionManager
     * @param Config $config
     */
    public function __construct(
        RequestInterface $request,
        Session $session,
        SessionManagerInterface $sessionManager,
        Config $config
    ) {
        $this->request = $request;
        $this->session = $session;
        $this->sessionManager = $sessionManager;
        $this->config = $config;
    }

    /**
     * Transport datalayer to frontend local storage
     *
     * @param mixed $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData($subject, array $result): array
    {
        if (!$this->config->isEnabled() || 'customer_section_load' !== $this->request->getFullActionName()) {
            return $result;
        }

        $pixelData = $this->sessionManager->get($this->session);
        if ($pixelData) {
            $result['mf_fb_pixel_data'] = $pixelData;
        }

        //add only to customer section
        if ($this->session->getCustomerId()) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $customerData = $objectManager->get(
                \Magefan\FacebookPixelPlus\Model\GetDataForAdvancedMatching::class
            )->execute();

            if ($customerData) {
                $result['mf_fb_pixel_customer_data'] = $customerData;
            }
        }

        return $result;
    }
}
