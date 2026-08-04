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

use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapperInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

/**
 * Class InitializeCommand
 *
 */
class RefundQueryCommand implements CommandInterface
{
    const REMOVE_ARRAY = [
        'zp_trans_id',
        'amount',
        'description'
    ];

    private $mRefundId;

    /**
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     * @param HandlerInterface|null $handler
     * @param ValidatorInterface|null $validator
     * @param ErrorMessageMapperInterface|null $errorMessageMapper
     */
    public function __construct(
        private readonly BuilderInterface            $requestBuilder,
        private readonly TransferFactoryInterface    $transferFactory,
        private readonly ClientInterface             $client,
        private readonly LoggerInterface             $logger,
        private ?HandlerInterface                    $handler = null,
        private ?ValidatorInterface                  $validator = null,
        private ?ErrorMessageMapperInterface         $errorMessageMapper = null
    ) {
    }

    /**
     * @param $mRefundId
     * @return $this
     */
    public function setZaloRefundId($mRefundId): static
    {
        $this->mRefundId = $mRefundId;
        return $this;
    }
    /**
     * @param $requestData
     * @param string $mRefundId
     * @param bool $isCron
     * @return array
     * @throws \Exception
     */
    public function getRefundQuery($requestData): array
    {
        try {
            $transferO = $this->transferFactory->create(
                $requestData
            );

            return $this->client->placeRequest($transferO);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw new \Exception(__($exception->getMessage()));
        }
    }

    /**
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject): array
    {
        $result = $this->requestBuilder->build($commandSubject);
        foreach (self::REMOVE_ARRAY as $key) {
            unset($result[$key]);
        }
        $result['m_refund_id'] = $this->mRefundId;
        return $result;
    }

    public function execute(array $commandSubject)
    {
        // TODO: Implement execute() method.
    }
}
