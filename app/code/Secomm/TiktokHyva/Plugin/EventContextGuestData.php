<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Plugin;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote\Address;
use Throwable;
use Tiktok\Tiktok\Model\Event\Context\EventContext;

/**
 * Guest advanced matching — the vendor EventContext only reads customer-session
 * data, so guest checkout events carried no email/phone/name/location. Fall back
 * to the quote billing address when the session value is empty. Values stay raw
 * here; the vendor TiktokEvent hashes them (SHA-256) before sending.
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
class EventContextGuestData
{
    public function __construct(private readonly CheckoutSession $checkoutSession)
    {
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetEmail(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getEmail();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetPhone(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getTelephone();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetFirstName(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getFirstname();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetLastName(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getLastname();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCity(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getCity();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetState(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getRegionCode();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetCountry(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getCountryId();
    }

    /**
     * @param EventContext $subject
     * @param string|null $result
     * @return string|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetZipCode(EventContext $subject, ?string $result): ?string
    {
        return $result ?: $this->getBilling()?->getPostcode();
    }

    /**
     * Guest billing address from the active quote (null when no quote/none entered)
     *
     * @return Address|null
     */
    private function getBilling(): ?Address
    {
        try {
            $billing = $this->checkoutSession->getQuote()->getBillingAddress();

            return $billing && ($billing->getEmail() || $billing->getTelephone()) ? $billing : null;
        } catch (Throwable) {
            // No active quote (e.g. PDP/cron context) — nothing to fall back to.
            return null;
        }
    }
}
