<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Service;

/**
 * Process-scoped, EXACT-REFUND, ONE-SHOT authorization for the gateway
 * refund command to SKIP the provider call (TASK-CG6BM7 corrective round
 * 7, F28 redesign).
 *
 * Why keyed by credit memo id: a credit memo entity is created for exactly
 * one refund attempt (two refunds on one order = two credit memos), so the
 * id identifies THIS refund precisely - an order key would let a second
 * refund inherit the first one's skip and run core accounting with the
 * provider never asked for ITS money.
 *
 * Contract (fail-closed):
 *  - authorize($cmId)   the plugin pins "provider outcome already known"
 *                       immediately BEFORE invoking the core flow;
 *  - consume($cmId)     one-shot: RefundCommand::prepare() consumes the
 *                       authorization when the core accounting (via
 *                       Payment::refund) reaches the gateway command - the
 *                       provider is skipped exactly once; a second consume
 *                       for the same credit memo returns false;
 *  - clear($cmId)       idempotent cleanup in the plugin's finally: if the
 *                       core flow threw BEFORE the gateway command consumed
 *                       the authorization (or the skip was never consumed),
 *                       the pin is dropped - nothing leaks to the next
 *                       refund attempt. Fail-closed: without an
 *                       authorization, prepare() builds a normal provider
 *                       request and the caller (plugin) aborts.
 *
 * Magento DI shares one instance per scope, so admin request AND cron
 * worker each get their own isolated marker: authorizations never leak
 * across refunds or processes.
 *
 * API CONTRACT (FROZEN): the method names, signatures and one-shot
 * semantics below are the cross-lane contract - do not rename.
 */
class RefundOutcomeMarker
{
    /**
     * Authorized credit memo ids (consumed entries removed).
     *
     * @var array<int, true>
     */
    private array $authorizations = [];

    /**
     * Authorize a one-shot provider-skip for THIS exact credit memo id.
     *
     * @param int $creditMemoId
     * @return void
     */
    public function authorize(int $creditMemoId): void
    {
        $this->authorizations[$creditMemoId] = true;
    }

    /**
     * Consume the authorization. One-shot: true only the first time.
     *
     * @param int $creditMemoId
     * @return bool
     */
    public function consume(int $creditMemoId): bool
    {
        if (!isset($this->authorizations[$creditMemoId])) {
            return false;
        }

        unset($this->authorizations[$creditMemoId]);

        return true;
    }

    /**
     * Idempotent explicit cleanup (failure paths; try/finally).
     *
     * @param int $creditMemoId
     * @return void
     */
    public function clear(int $creditMemoId): void
    {
        unset($this->authorizations[$creditMemoId]);
    }
}
