<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Helper;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Secomm\ZaloPay\Api\Data\RefundInterface;
use Secomm\ZaloPay\Gateway\Request\AbstractDataBuilder;
use Secomm\ZaloPay\Model\RefundModel;

/**
 * Builds the v2/query_refund subject from a refund row's stored payload:
 * refresh the timestamp, drop the stale MAC, re-sign (official MAC keys:
 * app_id|m_refund_id|timestamp - the stored payload holds exactly these).
 *
 * Shared by RefundCronjob (stateful reconciliation) and the read-only
 * zalopay:diagnose CLI (--query-refund) so the signing logic is never
 * duplicated (TASK-MCHN2T).
 */
class RefundQuerySubjectBuilder
{
    /**
     * @param Json         $serializer
     * @param DateTime     $dateTime
     * @param Authorization $authorization
     */
    public function __construct(
        private readonly Json          $serializer,
        private readonly DateTime      $dateTime,
        private readonly Authorization $authorization
    ) {
    }

    /**
     * Rebuild the v2/query_refund subject from the stored payload.
     *
     * @param RefundModel $refund
     * @return array
     * @throws LocalizedException Malformed stored payload.
     */
    public function build(RefundModel $refund): array
    {
        try {
            $commandSubject = $this->serializer->unserialize((string)$refund->getAdditionalInformation());
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(
                __('ZaloPay refund row #%1 has a malformed stored query payload.', (int)$refund->getId())
            );
        }

        if (!is_array($commandSubject) || empty($commandSubject[RefundInterface::M_REFUND_ID])) {
            throw new LocalizedException(
                __('ZaloPay refund row #%1 has a malformed stored query payload.', (int)$refund->getId())
            );
        }

        $commandSubject[AbstractDataBuilder::TIMESTAMP] = $this->dateTime->timestamp() * 1000;
        //Remove old Mac
        unset($commandSubject[AbstractDataBuilder::MAC]);
        $commandSubject[AbstractDataBuilder::MAC] = $this->authorization->getMac($commandSubject);

        return $commandSubject;
    }
}
