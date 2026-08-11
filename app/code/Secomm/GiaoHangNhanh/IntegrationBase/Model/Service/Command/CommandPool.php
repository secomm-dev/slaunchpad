<?php declare(strict_types=1);
/************************************************************
 *  * @author    Secomm Teams
 * *  @project   Boolfly Integration
 */
namespace Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\Command;

use Secomm\GiaoHangNhanh\IntegrationBase\Model\Service\CommandInterface;
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
