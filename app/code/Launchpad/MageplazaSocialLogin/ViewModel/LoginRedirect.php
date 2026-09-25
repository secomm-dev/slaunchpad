<?php
declare(strict_types=1);

namespace Launchpad\MageplazaSocialLogin\ViewModel;

use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Post-login destination for the Hyvä social-login popup (BUG-C97F09 / SLP-207).
 *
 * The popup logs in over AJAX and the vendor script always reloads the current
 * page, so `customer/startup/redirect_dashboard` only worked on the full-page
 * login form (Magento\Customer\Model\Account\Redirect). The popup template
 * reads the same flag and dashboard URL from here.
 */
class LoginRedirect implements ArgumentInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CustomerUrl $customerUrl
    ) {
    }

    public function isRedirectToDashboard(): bool
    {
        return $this->scopeConfig->isSetFlag(
            CustomerUrl::XML_PATH_CUSTOMER_STARTUP_REDIRECT_TO_DASHBOARD,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getDashboardUrl(): string
    {
        return $this->customerUrl->getDashboardUrl();
    }
}
