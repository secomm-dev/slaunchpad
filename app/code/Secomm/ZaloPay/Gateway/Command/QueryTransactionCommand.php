<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

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

/**
 * Queries the authoritative status of a ZaloPay transaction (v2/query).
 *
 * Unlike GetPayUrlCommand this returns the FULL provider response
 * (return_code, amount, zp_trans_id, ...) so the caller can verify the
 * payment server-side without trusting the browser redirect.
 */
class QueryTransactionCommand implements CommandInterface
{
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
        private readonly BuilderInterface         $requestBuilder,
        private readonly TransferFactoryInterface $transferFactory,
        private readonly ClientInterface          $client,
        private readonly ArrayResultFactory       $resultFactory,
        private readonly ValidatorInterface       $validator
    ) {
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
            throw new CommandException(
                __($response[AbstractResponseValidator::RESPONSE_MESSAGE] ?? 'ZaloPay status query failed.')
            );
        }

        return $this->resultFactory->create(['array' => $response]);
    }

    /**
     * Public so the QueryGenerateMac plugin can MAC-sign the built payload.
     *
     * @param array $commandSubject
     * @return array
     */
    public function buildRequestData(array $commandSubject): array
    {
        return $this->requestBuilder->build($commandSubject);
    }
}
