<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\MoMo\Gateway\Command;

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
 * Queries the authoritative status of a MoMo transaction (v2/query) for the
 * browser Return path (MOMO-01).
 *
 * Unlike GetPayUrlCommand this returns the FULL provider response (resultCode,
 * amount, transId, ...) so the caller can verify the payment server-side
 * without trusting the browser redirect. The response identity is validated
 * (QueryValidator: merchant identity + orderId/requestId echoes against the
 * EXACT query request this command just sent + the persisted attempt) INSIDE
 * the command — a mismatch throws CommandException: NO mutation, treated as
 * a verification failure (customer-safe exception, attempt state untouched).
 * MoMo signs the query REQUEST; the query RESPONSE carries no signature —
 * the merchant-initiated HTTPS call IS the server-side verification.
 */
class QueryTransactionCommand implements CommandInterface
{
    /**
     * QueryTransactionCommand constructor.
     *
     * @param BuilderInterface $requestBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface $client
     * @param ArrayResultFactory $resultFactory
     * @param ValidatorInterface $validator
     */
    public function __construct(
        private readonly BuilderInterface $requestBuilder,
        private readonly TransferFactoryInterface $transferFactory,
        private readonly ClientInterface $client,
        private readonly ArrayResultFactory $resultFactory,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Run the v2/query request and validate the response against the exact
     * request just sent + the attempt carried in the subject.
     *
     * @param array $commandSubject expects: order_ref, attempt
     *        (PaymentAttemptInterface).
     * @return ArrayResult|ResultInterface|null
     * @throws CommandException When the response fails validation — NO
     *         mutation follows, the caller treats the payment as unverified.
     * @throws ClientException
     * @throws ConverterException
     */
    public function execute(array $commandSubject): ResultInterface|ArrayResult|null
    {
        $queryRequest = $this->requestBuilder->build($commandSubject);
        $transfer = $this->transferFactory->create($queryRequest);
        $response = $this->client->placeRequest($transfer);
        $result = $this->validator->validate(
            array_merge($commandSubject, ['response' => $response, 'query_request' => $queryRequest])
        );

        if (!$result->isValid()) {
            throw new CommandException(__('MoMo payment verification failed.'));
        }

        return $this->resultFactory->create(['array' => $response]);
    }
}
