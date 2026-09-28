<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;

/**
 * CompleteRegistration lead value. TikTok diagnostics reject a missing value AND a
 * zero value ("must be a number > 0") — registrations carry a configurable lead
 * value (default 1000 VND, per website scope) so the event always ships a valid one.
 */
class TiktokEventValueDefaults
{
    private const XPATH_COMPLETE_REGISTRATION_VALUE = 'tiktok/pixel_tracking/complete_registration_value';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * @param TiktokEvent $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetDataElement(TiktokEvent $subject, array $result): array
    {
        if (($result['event'] ?? null) !== 'CompleteRegistration') {
            return $result;
        }

        $value = (float) $this->scopeConfig->getValue(
            self::XPATH_COMPLETE_REGISTRATION_VALUE,
            ScopeInterface::SCOPE_WEBSITES,
            $subject->getScopeManager()->getWebsiteId()
        );

        if ($value > 0) {
            // Keep the integer form when whole — TikTok wants a clean number.
            $result['properties']['value'] = $value === (float) (int) $value ? (int) $value : $value;
        }

        // MUST return the result — void after-plugins null the interceptor's value.
        return $result;
    }
}
