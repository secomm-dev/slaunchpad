<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration;

use Secomm\GiaoHangNhanh\Model\Integration\Command\CommandException;

/**
 * Interface CommandInterface
 * @package Secomm\GiaoHangNhanh\Model\Integration
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
