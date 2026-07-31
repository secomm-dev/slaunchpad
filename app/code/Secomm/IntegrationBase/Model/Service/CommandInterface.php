<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\IntegrationBase\Model\Service;

use Secomm\IntegrationBase\Model\Service\Command\CommandException;

/**
 * Interface CommandInterface
 * @package Secomm\IntegrationBase\Model\Service
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
