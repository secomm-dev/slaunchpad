<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Gateway\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;

/**
 * IpnCommand - handles IPN callbacks with POST JSON data
 *
 */
class IpnCommand implements CommandInterface
{
    /**
     * IpnCommand constructor.
     *
     * @param CommandInterface $updateDetailsCommand
     * @param CommandInterface $updateOrderCommand
     */
    public function __construct(
        private readonly CommandInterface $updateDetailsCommand,
        private readonly CommandInterface $updateOrderCommand
    ) {
    }

    /**
     * Execute IPN command
     *
     * @param array $commandSubject
     * @return ResultInterface|void|null
     * @throws LocalizedException
     * @throws CommandException
     */
    public function execute(array $commandSubject)
    {
        $this->updateDetailsCommand->execute($commandSubject);
        $this->updateOrderCommand->execute($commandSubject);
    }
}
