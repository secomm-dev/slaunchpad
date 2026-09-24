<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\CodRisk\Model\Audit;

use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Secomm\CodRisk\Model\Audit\AuditLogFactory;

/**
 * Writes administrative audit rows (who changed what, when — spec nguồn §16).
 *
 * Only called from admin-context flows; the backend session is the actor source.
 */
class AuditWriter
{
    public function __construct(
        private readonly AuditLogFactory $auditLogFactory,
        private readonly BackendAuthSession $backendAuthSession,
    ) {
    }

    public function log(
        string $entityType,
        ?int $entityId,
        string $action,
        ?string $oldValue = null,
        ?string $newValue = null,
        string $note = '',
    ): void {
        $entry = $this->auditLogFactory->create();
        $entry->setData([
            'entity_type' => $entityType,
            'entity_id_ref' => $entityId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'admin_username' => $this->getAdminUsername(),
            'note' => $note,
        ]);
        $entry->save();
    }

    private function getAdminUsername(): ?string
    {
        $user = $this->backendAuthSession->getUser();

        return $user !== null ? (string)$user->getUsername() : null;
    }
}
