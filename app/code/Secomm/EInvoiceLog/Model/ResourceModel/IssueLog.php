<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IssueLog extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('secomm_einvoice_issue_log', 'entity_id');
    }
}
