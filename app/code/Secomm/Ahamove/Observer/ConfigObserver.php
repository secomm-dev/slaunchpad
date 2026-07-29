<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Observer;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Secomm\Ahamove\Helper\Generic;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Express;
use Secomm\Ahamove\Model\Carrier\ShippingMethod\Standard;
use Secomm\PackagingManager\Helper\Data;
use StripeIntegration\Payments\Exception\SilentException;

class ConfigObserver implements ObserverInterface
{
    public function __construct(
        protected RequestInterface $request,
        protected Generic          $genericHelper,
        protected WriterInterface  $configWriter
    ) {
    }

    public function execute(EventObserver $observer)
    {
        try {
            if (!$this->genericHelper->isAdmin()) {
                return;
            }
            $listServicesInsert = [];
            $listServicesRemove = [];
            $modelStandard = $this->genericHelper->getModelCarrier(Standard::AHAMOVE_STANDARD_CARRIER_CODE);
            $modelExpress = $this->genericHelper->getModelCarrier(Express::AHAMOVE_EXPRESS_CARRIER_CODE);
            if (empty($oldService)) {
                $oldService = [];
            } else {
                $oldService = explode(',', $oldService);
            }
            if ($this->genericHelper->isEnableAhamoveStandard()) {
                foreach (array_keys($modelStandard->getService()) as $service) {
                    if (!in_array($service, $oldService)) {
                        $listServicesInsert[] = Standard::AHAMOVE_STANDARD_CARRIER_CODE . '_' . $service;
                    }
                }
            } else {
                foreach (array_keys($modelStandard->getService()) as $service) {
                    $listServicesRemove[] = Standard::AHAMOVE_STANDARD_CARRIER_CODE . '_' . $service;
                }
            }
            if ($this->genericHelper->isEnableAhamoveExpress()) {
                foreach (array_keys($modelExpress->getService()) as $service) {
                    if (!in_array($service, $oldService)) {
                        $listServicesInsert[] = Express::AHAMOVE_EXPRESS_CARRIER_CODE . '_' . $service;
                    }
                }
            } else {
                foreach (array_keys($modelExpress->getService()) as $service) {
                    $listServicesRemove[] = Express::AHAMOVE_EXPRESS_CARRIER_CODE . '_' . $service;
                }
            }
            $oldService = $this->genericHelper->getListServiceConfig();
            if (empty($oldService)) {
                $oldService = [];
            } else {
                $oldService = explode(',', $oldService);
            }
            if (empty($listServicesInsert) && empty($listServicesRemove)) {
                return;
            } else {
                $listServices = array_diff(array_merge($oldService, $listServicesInsert), $listServicesRemove);
                $this->configWriter->save(Data::LIST_SERVICES_PATH, implode(',', array_unique($listServices)));
                $this->genericHelper->flushCache();
            }
        } catch (SilentException $e) {
        }
    }
}
