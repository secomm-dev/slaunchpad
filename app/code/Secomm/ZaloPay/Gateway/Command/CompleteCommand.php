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
 * CompleteCommand - handles Return callback (GET params)
 */
class CompleteCommand implements CommandInterface
{
    /**
     * @var CompleteUpdateDetailsCommand
     */
    private CompleteUpdateDetailsCommand $updateDetailsCommand;

    /**
     * @var UpdateOrderCommand
     */
    private UpdateOrderCommand $updateOrderCommand;

    /**
     * CompleteCommand constructor.
     *
     * @param CompleteUpdateDetailsCommand $updateDetailsCommand
     * @param UpdateOrderCommand $updateOrderCommand
     */
    public function __construct(
        CompleteUpdateDetailsCommand $updateDetailsCommand,
        UpdateOrderCommand          $updateOrderCommand
    ) {
        $this->updateDetailsCommand = $updateDetailsCommand;
        $this->updateOrderCommand = $updateOrderCommand;
    }

    /**
     * Execute complete command for Return callback
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
