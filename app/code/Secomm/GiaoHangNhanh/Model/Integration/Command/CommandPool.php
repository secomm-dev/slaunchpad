<?php declare(strict_types=1);
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Team
 * *  @project   Secomm Integration
 */
namespace Secomm\GiaoHangNhanh\Model\Integration\Command;

use Secomm\GiaoHangNhanh\Model\Integration\CommandInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\ObjectManager\TMap;
use Magento\Framework\ObjectManager\TMapFactory;

class CommandPool implements CommandPoolInterface
{
    /** @var CommandInterface[] | TMap */
    private $commands;

    /**
     * @param TMapFactory $tmapFactory
     * @param array $commands
     */
    public function __construct(
        TMapFactory $tmapFactory,
        array $commands = []
    ) {
        $this->commands = $tmapFactory->create(
            [
                'array' => $commands,
                'type' => CommandInterface::class
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function get($commandCode)
    {
        if (!isset($this->commands[$commandCode])) {
            throw new NotFoundException(
                __('The "%1" command doesn\'t exist. Verify the command and try again.', $commandCode)
            );
        }
        return $this->commands[$commandCode];
    }
}
