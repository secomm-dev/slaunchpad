<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Console\Command;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Secomm\ZaloPay\Gateway\Command\RefundQueryCommand;
use Secomm\ZaloPay\Gateway\Helper\RefundQuerySubjectBuilder;
use Secomm\ZaloPay\Gateway\Validator\AbstractResponseValidator;
use Secomm\ZaloPay\Model\Config;
use Secomm\ZaloPay\Model\ResourceModel\RefundModel\RefundCollectionFactory;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only operator diagnostics for the ZaloPay gateway (TASK-MCHN2T).
 *
 * Default run reports configuration health (mode, enablement, credential
 * PRESENCE - never values - and derivable endpoint paths) and exits non-zero
 * for invalid or incomplete configuration. The explicit query options reuse
 * the canonical gateway commands: `query_transaction` through the command
 * pool and the same stored-payload re-signing path the refund cron uses.
 * No payment is created and no refund is initiated; refund rows are never
 * mutated (no budget consumption, no state transition).
 */
class DiagnoseCommand extends ConsoleCommand
{
    private const OPTION_JSON = 'json';
    private const OPTION_QUERY_PAYMENT = 'query-payment';
    private const OPTION_QUERY_REFUND = 'query-refund';

    /**
     * Base URL config path (default scope) used for the derivable endpoints.
     */
    private const XML_PATH_BASE_URL = 'web/unsecure/base_url';

    /**
     * Response fields safe to print (never secrets, MACs or signatures).
     *
     * @var array
     */
    private array $safeResponseKeys = [
        AbstractResponseValidator::RETURN_CODE,
        AbstractResponseValidator::SUB_RETURN_CODE,
        AbstractResponseValidator::RESPONSE_MESSAGE,
        AbstractResponseValidator::ZP_TRANS_ID,
        AbstractResponseValidator::REFUND_ID,
        'm_refund_id',
    ];

    /**
     * @param ConfigInterface $config ZaloPayConfig (payment/zalopay/*)
     * @param CommandPoolInterface $commandPool ZaloPayCommandPool
     * @param RefundQueryCommand $refundQueryCommand
     * @param RefundQuerySubjectBuilder $refundQuerySubjectBuilder
     * @param RefundCollectionFactory $refundCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ConfigInterface              $config,
        private readonly CommandPoolInterface         $commandPool,
        private readonly RefundQueryCommand           $refundQueryCommand,
        private readonly RefundQuerySubjectBuilder    $refundQuerySubjectBuilder,
        private readonly RefundCollectionFactory      $refundCollectionFactory,
        private readonly ScopeConfigInterface         $scopeConfig,
        ?string                                       $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName('zalopay:diagnose');
        $this->setDescription(
            'Read-only ZaloPay operator diagnostics: configuration health report '
            . 'and status queries through the canonical gateway commands.'
        );
        $this->addOption(
            self::OPTION_JSON,
            null,
            InputOption::VALUE_NONE,
            'Emit machine-readable JSON instead of human-readable text.'
        );
        $this->addOption(
            self::OPTION_QUERY_PAYMENT,
            null,
            InputOption::VALUE_REQUIRED,
            'Query the provider for the payment status of an app_trans_id (read-only).'
        );
        $this->addOption(
            self::OPTION_QUERY_REFUND,
            null,
            InputOption::VALUE_REQUIRED,
            'Query the provider for the status of a local m_refund_id (read-only).'
        );
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $queryPayment = (string)$input->getOption(self::OPTION_QUERY_PAYMENT);
        $queryRefund = (string)$input->getOption(self::OPTION_QUERY_REFUND);

        if ($queryPayment !== '' && $queryRefund !== '') {
            return $this->writeError($input, $output, '--query-payment and --query-refund are mutually exclusive.');
        }

        if ($queryPayment !== '') {
            return $this->queryPayment($queryPayment, $input, $output);
        }

        if ($queryRefund !== '') {
            return $this->queryRefund($queryRefund, $input, $output);
        }

        return $this->reportConfig($input, $output);
    }

    /**
     * Default run: report configuration health without leaking any value.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function reportConfig(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->buildReport();
        $exit = $report['configuration_complete'] ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;

        if ($input->getOption(self::OPTION_JSON)) {
            $output->writeln(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $exit;
        }

        foreach ($this->renderReport($report) as $line) {
            $output->writeln((string)$line);
        }

        return $exit;
    }

    /**
     * Query the provider for one payment status (read-only).
     *
     * @param string $appTransId
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function queryPayment(string $appTransId, InputInterface $input, OutputInterface $output): int
    {
        $report = $this->buildReport();
        if (!$report['configuration_complete']) {
            return $this->writeError($input, $output, 'Configuration is incomplete - fix it before querying.');
        }

        try {
            /** @var CommandInterface $queryCommand */
            $queryCommand = $this->commandPool->get('query_transaction');
            $result = $queryCommand->execute([AbstractResponseValidator::TRANSACTION_ID => $appTransId]);
        } catch (\Throwable $exception) {
            return $this->writeError(
                $input,
                $output,
                sprintf('Payment query failed: %s', $exception->getMessage())
            );
        }

        $response = is_array($result) ? $result : (method_exists($result, 'get') ? $result->get() : []);
        $json = ['query' => 'payment', 'app_trans_id' => $appTransId, 'response' => $this->pickSafeKeys($response)];
        $lines = [
            sprintf('Payment query for app_trans_id "%s":', $appTransId),
            ...$this->renderResponse($json['response']),
        ];

        return $this->write($input, $output, $lines, $json);
    }

    /**
     * Query the provider for one refund status via the local refund identity (read-only).
     *
     * @param string $mRefundId
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    private function queryRefund(string $mRefundId, InputInterface $input, OutputInterface $output): int
    {
        $refund = $this->resolveRefund($mRefundId);
        if ($refund === null) {
            return $this->writeError(
                $input,
                $output,
                sprintf('No local refund row matches m_refund_id "%s".', $mRefundId)
            );
        }

        try {
            $subject = $this->refundQuerySubjectBuilder->build($refund);
            $response = $this->refundQueryCommand->getRefundQuery($subject);
        } catch (\Throwable $exception) {
            return $this->writeError(
                $input,
                $output,
                sprintf('Refund query failed: %s', $exception->getMessage())
            );
        }

        $json = ['query' => 'refund', 'm_refund_id' => $mRefundId, 'response' => $this->pickSafeKeys($response)];
        $lines = [
            sprintf('Refund query for m_refund_id "%s":', $mRefundId),
            ...$this->renderResponse($json['response']),
        ];

        return $this->write($input, $output, $lines, $json);
    }

    /**
     * Configuration health report. Credential values are NEVER included -
     * only their presence.
     *
     * @return array
     */
    private function buildReport(): array
    {
        $mode = (bool)$this->config->getValue('sandbox_flag') ? 'SANDBOX' : 'LIVE';
        $gatewayUrl = $mode === 'SANDBOX'
            ? Config::SANDBOX_PAYMENT_ZALO_URL
            : Config::LIVE_PAYMENT_ZALO_URL;
        $credentials = [];
        foreach (['app_id', 'key1', 'key2', 'app_user'] as $field) {
            $credentials[$field] = (string)$this->config->getValue($field) !== '' ? 'set' : 'missing';
        }
        $baseUrl = (string)$this->scopeConfig->getValue(self::XML_PATH_BASE_URL);

        $report = [
            'mode' => $mode,
            'gateway_url' => $gatewayUrl,
            'active' => (bool)$this->config->getValue('active'),
            'debug_logging' => (bool)$this->config->getValue('debug'),
            'credentials' => $credentials,
            'endpoints' => [
                'start' => $baseUrl !== '' ? $baseUrl . 'zalopay/payment/start' : '/zalopay/payment/start',
                'return' => $baseUrl !== '' ? $baseUrl . 'zalopay/payment/return' : '/zalopay/payment/return',
                'ipn' => $baseUrl !== '' ? $baseUrl . 'zalopay/payment/ipn' : '/zalopay/payment/ipn',
            ],
        ];

        $required = ['app_id', 'key1', 'key2'];
        $missing = array_filter(
            $required,
            static fn (string $field): bool => $credentials[$field] === 'missing'
        );
        $report['missing_required_credentials'] = array_values($missing);
        $report['configuration_complete'] = $report['active'] && $missing === [];

        return $report;
    }

    /**
     * Resolve the local refund row by its stable provider identity.
     *
     * @param string $mRefundId
     * @return \Secomm\ZaloPay\Model\RefundModel|null
     */
    private function resolveRefund(string $mRefundId): ?\Secomm\ZaloPay\Model\RefundModel
    {
        $collection = $this->refundCollectionFactory->create();
        $collection->addFieldToFilter(\Secomm\ZaloPay\Api\Data\RefundInterface::M_REFUND_ID, $mRefundId);
        $collection->setPageSize(1);

        // getFirstItem() returns an EMPTY model (not null) on no match.
        $refund = $collection->getFirstItem();

        return $refund->getId() ? $refund : null;
    }

    /**
     * Keep only the whitelisted, non-sensitive response fields.
     *
     * @param array $response
     * @return array
     */
    private function pickSafeKeys(array $response): array
    {
        $safe = [];
        foreach ($this->safeResponseKeys as $key) {
            if (array_key_exists($key, $response)) {
                $safe[$key] = $response[$key];
            }
        }

        return $safe;
    }

    /**
     * Render one "key: value" line per safe response field.
     *
     * @param array $response
     * @return array
     */
    private function renderResponse(array $response): array
    {
        $lines = [];
        foreach ($response as $key => $value) {
            $lines[] = sprintf('  %s: %s', $key, is_scalar($value) ? (string)$value : json_encode($value));
        }

        return $lines;
    }

    /**
     * Success output funnel: JSON for --json, text otherwise.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $lines
     * @param array $json
     * @return int
     */
    private function write(InputInterface $input, OutputInterface $output, array $lines, array $json): int
    {
        if ($input->getOption(self::OPTION_JSON)) {
            $output->writeln(json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return Cli::RETURN_SUCCESS;
        }

        foreach ($lines as $line) {
            $output->writeln((string)$line);
        }

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Error output funnel: {"error": "..."} for --json, <error> text otherwise.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $message
     * @return int
     */
    private function writeError(InputInterface $input, OutputInterface $output, string $message): int
    {
        if ($input->getOption(self::OPTION_JSON)) {
            $output->writeln(json_encode(['error' => $message], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf('<error>%s</error>', $message));

        return Cli::RETURN_FAILURE;
    }

    /**
     * Print the default configuration report in text form.
     *
     * @param array $report
     * @return array
     */
    private function renderReport(array $report): array
    {
        $lines = [
            sprintf('ZaloPay mode: %s', $report['mode']),
            sprintf('Gateway URL: %s', $report['gateway_url']),
            sprintf('Enabled: %s', $report['active'] ? 'yes' : 'no'),
            sprintf('Debug logging: %s', $report['debug_logging'] ? 'on' : 'off'),
            'Credentials (presence only):',
        ];
        foreach ($report['credentials'] as $field => $status) {
            $lines[] = sprintf('  %s: %s', $field, $status);
        }
        $lines[] = 'Endpoints:';
        foreach ($report['endpoints'] as $name => $url) {
            $lines[] = sprintf('  %s: %s', $name, $url);
        }
        if (!$report['configuration_complete']) {
            $lines[] = sprintf(
                '<error>Configuration incomplete - missing: %s (or method disabled).</error>',
                implode(', ', $report['missing_required_credentials'] ?: ['active = disabled'])
            );
        }

        return $lines;
    }
}
