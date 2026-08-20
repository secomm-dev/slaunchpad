<?php
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command\CommandException;

/**
 * Interface CommandInterface
 * @package Secomm\GiaoHangNhanh\IntegrationBase\Model\Service
 */
interface CommandInterface
{
    /**
     * @param array $subject
     * @return null| Command\ResultInterface
     * @throws CommandException
     */
    public function execute(array $subject);
}
