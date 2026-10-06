<?php

declare(strict_types=1);

namespace Secomm\EInvoiceLog\Model\ResourceModel\IssueLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Secomm\EInvoiceLog\Model\IssueLog;
use Secomm\EInvoiceLog\Model\ResourceModel\IssueLog as IssueLogResource;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(IssueLog::class, IssueLogResource::class);
    }
}
