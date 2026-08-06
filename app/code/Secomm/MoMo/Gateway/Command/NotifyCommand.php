<?php
/**
 * MoMo Notify (IPN) command: validate the inbound MoMo result, then finalize
 * the order (transaction + invoice + state). No outbound HTTP call.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2024 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;

class NotifyCommand implements CommandInterface
{
    /**
     * Constructor
     *
     * @param ValidatorInterface $validator
     * @param HandlerInterface $handler
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly HandlerInterface $handler
    ) {
    }

    /**
     * @inheritdoc
     * @throws CommandException
     */
    public function execute(array $commandSubject): void
    {
        $response = SubjectReader::readResponse($commandSubject);

        $result = $this->validator->validate($commandSubject);
        if (!$result->isValid()) {
            throw new CommandException(
                __(implode(' ', $result->getFailsDescription()))
            );
        }

        $this->handler->handle($commandSubject, $response);
    }
}
