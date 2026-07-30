<?php
/************************************************************
 * *
 *  * Copyright © Secomm. All rights reserved.
 *  * See COPYING.txt for license details.
 *  *
 *  * @author    Secomm Teams
 * *  @project   ZaloPay
 */

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
    /**
     * @var BuilderInterface
     */
    private BuilderInterface $requestBuilder;

    /**
     * @var TransferFactoryInterface
     */
    private TransferFactoryInterface $transferFactory;

    /**
     * @var ClientInterface
     */
    private ClientInterface $client;

    /**
     * @var HandlerInterface
     */
    private HandlerInterface $handler;

    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $validator;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ErrorMessageMapperInterface
     */
    private ?ErrorMessageMapperInterface $errorMessageMapper;
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
        BuilderInterface            $requestBuilder,
        TransferFactoryInterface    $transferFactory,
        ClientInterface             $client,
        LoggerInterface             $logger,
        HandlerInterface            $handler = null,
        ValidatorInterface          $validator = null,
        ErrorMessageMapperInterface $errorMessageMapper = null
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->handler = $handler;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->errorMessageMapper = $errorMessageMapper;
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
