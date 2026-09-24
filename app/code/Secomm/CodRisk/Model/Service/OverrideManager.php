<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Secomm\CodRisk\Api\CodRiskEvaluatorInterface;
use Secomm\CodRisk\Api\Data\CodRiskDecisionInterface;
use Secomm\CodRisk\Model\Audit\AuditLog;
use Secomm\CodRisk\Model\Audit\AuditWriter;
use Secomm\CodRisk\Model\CodRiskOverride;
use Secomm\CodRisk\Model\CodRiskOverrideFactory;
use Secomm\CodRisk\Model\Data\CodRiskContext;
use Secomm\CodRisk\Model\Phone\PhoneNormalizer;

/**
 * Order-level manual override (D-04): records base -> effective for ONE order
 * with mandatory reason + audit. It never mutates list records or history.
 */
class OverrideManager
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CodRiskEvaluatorInterface $evaluator,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly CodRiskOverrideFactory $overrideFactory,
        private readonly AuditWriter $auditWriter,
    ) {
    }

    public function createForOrder(int $orderId, string $reason, string $note, string $adminUsername): CodRiskOverride
    {
        if (trim($reason) === '') {
            throw new LocalizedException(new Phrase('Override reason is required.'));
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(new Phrase('Order does not exist.'), $e);
        }

        $phone = (string)$order->getShippingAddress()?->getTelephone();
        $normalized = $this->phoneNormalizer->normalize($phone);
        if ($normalized === null) {
            throw new LocalizedException(new Phrase('Order shipping phone is not a valid Vietnamese number.'));
        }

        $websiteId = (int)$order->getStore()->getWebsiteId();
        $decision = $this->evaluator->evaluate(new CodRiskContext(
            $this->phoneNormalizer,
            $phone,
            $websiteId,
            null,
            $orderId,
            $order->getCustomerId() !== null ? (int)$order->getCustomerId() : null
        ));

        if ($decision->getDecision() !== CodRiskDecisionInterface::BLOCK) {
            throw new LocalizedException(
                new Phrase('Override is only available while the current decision is BLOCK.')
            );
        }

        /** @var CodRiskOverride $override */
        $override = $this->overrideFactory->create();
        $override->setData([
            'order_id' => $orderId,
            'normalized_phone' => $normalized,
            'website_id' => $websiteId,
            'base_decision' => CodRiskDecisionInterface::BLOCK,
            'override_decision' => CodRiskDecisionInterface::ALLOW,
            'reason' => $reason,
            'note' => $note,
            'admin_username' => $adminUsername,
        ]);
        $override->save();

        $this->auditWriter->log(
            AuditLog::ENTITY_TYPE_OVERRIDE,
            (int)$override->getId(),
            'overridden',
            'BLOCK',
            'ALLOW',
            sprintf('Order #%s by %s: %s', $order->getIncrementId(), $adminUsername, $reason)
        );

        return $override;
    }
}
