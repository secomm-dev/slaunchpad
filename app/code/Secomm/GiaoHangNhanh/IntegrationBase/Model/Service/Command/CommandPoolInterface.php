<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command;

use Magento\Framework\Exception\NotFoundException;
use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\CommandInterface;

interface CommandPoolInterface
{
    /**
     * Retrieves operation
     *
     * @param string $commandCode
     * @return CommandInterface
     * @throws NotFoundException
     */
    public function get($commandCode);
}
