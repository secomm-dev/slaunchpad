<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

namespace Secomm\Ahamove\Helper;

use Exception;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Secomm\PackagingManager\Model\Config\Source\ListServices;

class Generic extends AbstractHelper
{
    const AHAMOVE_STANDARD_ACTIVE_PATH = 'carriers/ahamove_standard/active';

    const AHAMOVE_EXPRESS_ACTIVE_PATH = 'carriers/ahamove_express/active';

    public function __construct(
        protected State             $appState,
        protected ListServices      $listServices,
        protected TypeListInterface $cacheTypeList,
        protected Pool              $cacheFrontendPool,
        Context                     $context,
    )
    {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function isAdmin(): bool
    {
        try {
            $areaCode = $this->appState->getAreaCode();
            return $areaCode == FrontNameResolver::AREA_CODE;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isEnableAhamoveStandard(): bool
    {
        return $this->scopeConfig->isSetFlag(self::AHAMOVE_STANDARD_ACTIVE_PATH);
    }

    /**
     * @return bool
     */
    public function isEnableAhamoveExpress(): bool
    {
        return $this->scopeConfig->isSetFlag(self::AHAMOVE_EXPRESS_ACTIVE_PATH);
    }

    /**
     * @param $carrier
     * @return bool|mixed
     */
    public function getModelCarrier($carrier)
    {
        return $this->listServices->getModelCarrier($carrier);
    }

    /**
     * @return string
     */
    public function getListServiceConfig(): string
    {
        return (string)$this->scopeConfig->getValue(\Secomm\PackagingManager\Helper\Data::LIST_SERVICES_PATH);
    }

    /**
     * flushCache after change config
     *
     * @return void
     */
    public function flushCache(): void
    {
        $listTypes = [
            'config',
            'full_page',
            'config_webservice'
        ];

        foreach ($listTypes as $type) {
            $this->cacheTypeList->cleanType($type);
        }
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

}
