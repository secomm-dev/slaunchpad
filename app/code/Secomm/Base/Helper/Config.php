<?php

namespace Secomm\Base\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    protected string $scope = ScopeInterface::SCOPE_STORE;

    protected function getConfig(string $path)
    {
        return $this->scopeConfig->getValue($path, $this->scope);
    }

    protected function changeScope(string $scope): static
    {
        $this->scope = $scope;
        return $this;
    }
}
