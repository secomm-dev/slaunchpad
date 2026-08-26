<?php
declare(strict_types=1);

namespace Secomm\AiCommerce\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Save-time canonicalization of the AI read endpoint base path.
 *
 * Delegates to Model\Config::normalizeEndpointPath() — the single
 * normalization authority — so the stored value is always canonical and
 * consumers (router, llms.txt advertisement) never see raw admin input.
 * An invalid entered value is silently stored as the default "ai" rather
 * than rejected, keeping the config save flow unblocked; the same fallback
 * applies at read time anyway.
 */
class EndpointPath extends Value
{
    /**
     * Normalize the value before it is persisted.
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        $this->setValue(\Secomm\AiCommerce\Model\Config::normalizeEndpointPath((string) $this->getValue()));

        return $this;
    }
}
