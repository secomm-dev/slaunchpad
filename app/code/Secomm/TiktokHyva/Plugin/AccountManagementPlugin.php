<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Plugin;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement;
use Secomm\TiktokHyva\Model\PendingEventTracker;
use Tiktok\Tiktok\Logger\TiktokLogger;

/**
 * CompleteRegistration — plugin on the account service instead of the
 * customer_register_success event: on this storefront accounts are created through
 * Mageplaza SocialLogin's popup (and potentially GraphQL), neither of which
 * dispatches that event. AccountManagement::createAccount* covers every creation
 * path (SocialLogin popup, CreatePost form, GraphQL); admin-created customers are
 * excluded by the tracker's admin-area guard.
 */
class AccountManagementPlugin
{
    public function __construct(
        private readonly PendingEventTracker $tracker,
        private readonly TiktokLogger $logger
    ) {
    }

    /**
     * MUST return the customer — an after plugin that returns nothing (void) nulls
     * the interceptor's $result and breaks every downstream caller.
     *
     * @param AccountManagement $subject
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreateAccount(
        AccountManagement $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        $this->tracker->track('CompleteRegistration');

        return $customer;
    }

    /**
     * @param AccountManagement $subject
     * @param CustomerInterface $customer
     * @return CustomerInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreateAccountWithPasswordHash(
        AccountManagement $subject,
        CustomerInterface $customer
    ): CustomerInterface {
        $this->tracker->track('CompleteRegistration');

        return $customer;
    }
}
