<?php
/**
 * Resolves an UNKNOWN MoMo refund row by querying the provider
 * (/v2/gateway/api/refund/query). Query-only: the refund itself is NEVER
 * re-posted with a new identity — a resolution may only be evidenced by
 * the provider's own answer about the SAME refund identity.
 *
 * Manual, operator-invoked; each invocation mints a fresh query requestId
 * for the /refund/query call — never an automated reconciliation loop.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Payment\Gateway\Http\ClientException;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Secomm\MoMo\Gateway\Helper\Signature;
use Secomm\MoMo\Model\OrderRefBuilder;
use Secomm\MoMo\Service\RefundClassification;
use Secomm\MoMo\Service\RefundRequestManager;
use Secomm\MoMo\Service\RefundResultClassifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * momo:refund:resolve <requestId>
 */
class RefundResolveCommand extends Command
{
    public const NAME = 'momo:refund:resolve';

    /** @var RefundRequestRepositoryInterface */
    private $repository;

    /** @var RefundRequestManager */
    private $manager;

    /** @var RefundResultClassifier */
    private $classifier;

    /** @var Signature */
    private $signature;

    /** @var \Secomm\MoMo\Model\Config */
    private $config;

    /** @var \Magento\Payment\Gateway\Http\TransferFactoryInterface */
    private $transferFactory;

    /** @var \Magento\Payment\Gateway\Http\ClientInterface */
    private $client;

    /** @var State */
    private $state;

    /** @var OrderRefBuilder */
    private $orderRefBuilder;

    public function __construct(
        RefundRequestRepositoryInterface $repository,
        RefundRequestManager $manager,
        RefundResultClassifier $classifier,
        Signature $signature,
        \Secomm\MoMo\Model\Config $config,
        \Magento\Payment\Gateway\Http\TransferFactoryInterface $transferFactory,
        \Magento\Payment\Gateway\Http\ClientInterface $client,
        State $state,
        OrderRefBuilder $orderRefBuilder,
        ?string $name = null
    ) {
        $this->repository = $repository;
        $this->manager = $manager;
        $this->classifier = $classifier;
        $this->signature = $signature;
        $this->config = $config;
        $this->transferFactory = $transferFactory;
        $this->client = $client;
        $this->state = $state;
        $this->orderRefBuilder = $orderRefBuilder;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Resolve a MoMo refund row of unknown outcome by querying the provider (query-only)');
        $this->addArgument('requestId', InputArgument::REQUIRED, 'The stored refund requestId');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $requestId = (string)$input->getArgument('requestId');
        $row = $this->repository->getByRequestId($requestId);
        if ($row === null) {
            $output->writeln(sprintf('<error>No MoMo refund row for request id "%s".</error>', $requestId));
            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf(
            'Row #%d: status=%s reason=%s amount=%d refund_order_id=%s',
            $row->getEntityId(),
            $row->getStatus(),
            $row->getClassificationReason() ?: '-',
            $row->getAmount(),
            $row->getRefundOrderId()
        ));

        if ($row->isTerminal()) {
            $output->writeln('<comment>Row is already terminal; nothing to resolve.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        // The query is a DIFFERENT API operation than the refund submission:
        // it mints its own FRESH requestId per invocation and signs with it.
        // The stored refund requestId is the refund's provider idempotency
        // key — immutable submission evidence, never reused on the wire for
        // another operation.
        $queryRequestId = $this->orderRefBuilder->buildRefundQueryRequestId(
            $row->getRefundOrderId()
        );

        // MoMo signs the refund/query REQUEST; the response carries no
        // signature (same contract as refund/query sibling endpoints).
        $request = [
            'partnerCode' => $this->config->getPartnerCode(),
            'orderId' => $row->getRefundOrderId(),
            'requestId' => $queryRequestId,
            'lang' => 'vi',
        ];
        $request['signature'] = $this->signature->sign(
            [
                'accessKey' => $this->config->getAccessKey(),
                'orderId' => $request['orderId'],
                'partnerCode' => $request['partnerCode'],
                'requestId' => $request['requestId'],
            ],
            $this->config->getSecretKey()
        );

        try {
            $transfer = $this->transferFactory->create($request);
            $response = $this->client->placeRequest($transfer);
        } catch (ClientException $e) {
            $output->writeln(sprintf(
                '<comment>Provider query failed (transport); row stays unknown: %s</comment>',
                mb_substr($e->getMessage(), 0, 200)
            ));
            return Cli::RETURN_FAILURE;
        }

        $classification = $this->classifier->classifyQuery(
            [
                'refund_order_id' => $row->getRefundOrderId(),
                'amount' => $row->getAmount(),
                'query_request_id' => $queryRequestId,
                'partner_code' => $this->config->getPartnerCode(),
            ],
            $response
        );
        $changed = $this->manager->recordOutcome($row, $classification);

        if ($classification->isSuccess()) {
            $output->writeln(sprintf(
                '<info>RESOLVED: refund SUCCESS (transId %s). Accounting realignment:'
                . ' issue an offline credit memo for this amount if Magento shows it unrefunded.</info>',
                $classification->providerTransactionId
            ));
        } elseif ($classification->status === RefundRequestInterface::STATUS_FAILED) {
            $output->writeln('<info>RESOLVED: refund FAILED (provider-confirmed refusal); slot released.</info>');
        } else {
            $output->writeln(sprintf(
                '<comment>STILL UNKNOWN (reason=%s, changed=%s). Keep the row open; re-query later or'
                . ' contact MoMo support with the request id.</comment>',
                $classification->reason,
                $changed ? 'yes' : 'no'
            ));
        }

        return Cli::RETURN_SUCCESS;
    }
}
