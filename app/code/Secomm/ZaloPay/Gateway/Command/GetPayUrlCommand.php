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

use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\Result\ArrayResult;
use Magento\Payment\Gateway\Command\Result\ArrayResultFactory;
use Magento\Payment\Gateway\Command\ResultInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\ConverterException;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Gateway\Validator\ValidatorInterface;

class GetPayUrlCommand implements CommandInterface
{
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
     * @var ValidatorInterface
     */
    private ValidatorInterface $validator;

    /**
     * @var ArrayResultFactory
     */
    private ArrayResultFactory $resultFactory;

    /**
     * Constructor
     *
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param ArrayResultFactory $resultFactory
     * @param ValidatorInterface $validator
     */
    public function __construct(
        BuilderInterface         $requestBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface          $client,
        ArrayResultFactory       $resultFactory,
        ValidatorInterface       $validator
    ) {
        $this->requestBuilder = $requestBuilder;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->resultFactory = $resultFactory;
        $this->validator = $validator;
    }

    /**
     * @param array $commandSubject
     * @return ArrayResult|ResultInterface|null
     * @throws CommandException
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): ResultInterface|ArrayResult|null
    {
        $transferO = $this->transferFactory->create($this->buildRequestData($commandSubject));
        $response = $this->client->placeRequest($transferO);
        $result = $this->validator->validate(array_merge($commandSubject, ['response' => $response]));

        if (!$result->isValid()) {
            throw new CommandException(__($response['sub_return_message']));
        }

        return $this->resultFactory->create(
            [
                'array' => [
                    AbstractResponseValidator::PAY_URL => $response[AbstractResponseValidator::PAY_URL]
                ]
            ]
        );
    }

    /**
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject): array
    {
        return $this->requestBuilder->build($commandSubject);
    }
}
