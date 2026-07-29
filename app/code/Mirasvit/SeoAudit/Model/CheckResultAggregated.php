<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SeoAudit\Model;

use Magento\Framework\Model\AbstractModel;
use Mirasvit\SeoAudit\Api\Data\CheckResultAggregatedInterface;

class CheckResultAggregated extends AbstractModel implements CheckResultAggregatedInterface
{
    public function getId(): int
    {
        return (int)$this->getData(self::ID);
    }

    public function getJobId(): int
    {
        return (int)$this->getData(self::JOB_ID);
    }

    public function setJobId(int $jobId): CheckResultAggregatedInterface
    {
        return $this->setData(self::JOB_ID, $jobId);
    }

    public function getIdentifier(): string
    {
        return (string)$this->getData(self::IDENTIFIER);
    }

    public function setIdentifier(string $identifier): CheckResultAggregatedInterface
    {
        return $this->setData(self::IDENTIFIER, $identifier);
    }

    public function getTotal(): int
    {
        return (int)$this->getData(self::TOTAL);
    }

    public function setTotal(int $total): CheckResultAggregatedInterface
    {
        return $this->setData(self::TOTAL, $total);
    }

    public function getError(): int
    {
        return (int)$this->getData(self::ERROR);
    }

    public function setError(int $error): CheckResultAggregatedInterface
    {
        return $this->setData(self::ERROR, $error);
    }

    public function getWarning(): int
    {
        return (int)$this->getData(self::WARNING);
    }

    public function setWarning(int $warning): CheckResultAggregatedInterface
    {
        return $this->setData(self::WARNING, $warning);
    }

    public function getNotice(): int
    {
        return (int)$this->getData(self::NOTICE);
    }

    public function setNotice(int $notice): CheckResultAggregatedInterface
    {
        return $this->setData(self::NOTICE, $notice);
    }
}
