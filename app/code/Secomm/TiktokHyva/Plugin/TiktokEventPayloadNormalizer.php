<?php
declare(strict_types=1);

namespace Secomm\TiktokHyva\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Tiktok\Tiktok\Model\Event\TiktokEvent;

/**
 * Last-mile payload normalization on TiktokEvent::getDataElement() — every event
 * payload passes through here before serialization (S2S) and before the client
 * payload is built, so this is the single choke point covering all channels.
 *
 * - Prices land as EAV DECIMAL strings ("1256850.000000"); TikTok expects numbers.
 * - CompleteRegistration lead value (TikTok rejects missing/zero values).
 */
class TiktokEventPayloadNormalizer
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
        $properties = $result['properties'] ?? null;
        if (!is_array($properties)) {
            return $result;
        }

        if (isset($properties['value']) && is_numeric($properties['value'])) {
            $properties['value'] = $this->toNumber($properties['value']);
        }

        if (!empty($properties['contents']) && is_array($properties['contents'])) {
            foreach ($properties['contents'] as &$item) {
                if (is_array($item) && isset($item['price']) && is_numeric($item['price'])) {
                    $item['price'] = $this->toNumber($item['price']);
                }
            }
            unset($item);
        }

        if (($result['event'] ?? null) === 'CompleteRegistration') {
            $leadValue = (float) $this->scopeConfig->getValue(
                self::XPATH_COMPLETE_REGISTRATION_VALUE,
                ScopeInterface::SCOPE_WEBSITES,
                $subject->getScopeManager()->getWebsiteId()
            );
            if ($leadValue > 0) {
                $properties['value'] = $this->toNumber($leadValue);
            }
        }

        $result['properties'] = $properties;

        // MUST return the result — void after-plugins null the interceptor's value.
        return $result;
    }

    /**
     * Numeric form, integer when whole (cleaner payloads, TikTok accepts both)
     *
     * @param mixed $value
     * @return float|int
     */
    private function toNumber(mixed $value): float|int
    {
        $number = (float) $value;

        return $number === (float) (int) $number ? (int) $number : $number;
    }
}
