<?php
/**
 * Plugin to adjust active attributes used by Sales Rule
 */

namespace Mageplaza\ExtraFee\Plugin\Model\SalesRule\ResourceModel;

use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Mageplaza\ExtraFee\Helper\Data;

class RulePlugin
{
    private $helper;

    /**
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * After plugin for getActiveAttributes
     * Uses shared method from Data helper to avoid code duplication
     *
     * @param RuleResource $subject
     * @param array $result
     *
     * @return array
     */
    public function afterGetActiveAttributes(RuleResource $subject, $result)
    {
        if (!$this->helper->isEnabled()) {
            return $result;
        }

        // Get active attributes from shared helper method
        $ensureAttributes = $this->helper->getActiveAttributes();

        if (!is_array($result)) {
            $result = [];
        }

        // Merge with existing result
        foreach ($ensureAttributes as $code) {
            if (!in_array($code, $result, true)) {
                $result[] = $code;
            }
        }

        return $result;
    }

}
