<?php
declare(strict_types=1);

namespace Launchpad\MageplazaExtraFeeFix\Plugin\Model\Total\Creditmemo;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Mageplaza\ExtraFee\Helper\Data as ExtraFeeHelper;
use Mageplaza\ExtraFee\Model\Total\Creditmemo\ExtraFee;

class ExtraFeePlugin
{
    /**
     * @param ExtraFeeHelper $helper
     * @param PriceCurrencyInterface $priceCurrency
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly ExtraFeeHelper $helper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Fix PHP 8 non-numeric warning and formatting issues (e.g. "10,000" with thousands separators)
     * when refunding extra fee on creditmemo creation.
     *
     * @param ExtraFee $subject
     * @param callable $proceed
     * @param Creditmemo $creditmemo
     * @return ExtraFee
     * @throws LocalizedException
     */
    public function aroundCollect(
        ExtraFee $subject,
        callable $proceed,
        Creditmemo $creditmemo
    ): ExtraFee {
        $order          = $creditmemo->getOrder();
        $extraFeeTotals = $this->helper->getObjectExtraFeeTotals($creditmemo, $order);

        foreach ($extraFeeTotals as $fee) {
            $maxFee = round((float)$fee['value_incl_tax'], 2);
            if (!empty($fee['rf'])) {
                $rawAmount = $creditmemo->getData($fee['code']);

                if ($rawAmount !== null && $rawAmount !== ''
                    && $this->request->getControllerName() === 'order_creditmemo'
                    && $this->request->getActionName() !== 'updateQty'
                ) {
                    if (is_string($rawAmount)) {
                        $rawAmount = str_replace(',', '', trim($rawAmount));
                    }
                    $desiredAmount = $this->priceCurrency->round((float)$rawAmount);

                    if ($desiredAmount <= $maxFee) {
                        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $desiredAmount);
                        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $desiredAmount);
                        $creditmemo->setData($fee['code'], $desiredAmount);
                    } else {
                        throw new LocalizedException(
                            __('Maximum %1 amount allowed to refund is: %2', $fee['label'], $maxFee)
                        );
                    }
                } else {
                    $feeValue = $fee['apply_type'] == 2
                        ? round((float)$fee['value_incl_tax'], 2)
                        : round((float)$fee['value'], 2);
                    $baseFeeValue = $fee['apply_type'] == 2
                        ? round((float)$fee['base_value_incl_tax'], 2)
                        : round((float)$fee['base_value'], 2);

                    $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $feeValue);
                    $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseFeeValue);
                }
            }
        }

        return $subject;
    }
}
