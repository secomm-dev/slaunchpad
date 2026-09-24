<?php
/**
 * Lists MoMo refund request rows for operator inspection.
 *
 * @author    Secomm Teams
 * @copyright Copyright (c) 2026 Secomm (https://www.secomm.vn)
 * @package   Secomm_MoMo
 */
declare(strict_types=1);

namespace Secomm\MoMo\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Secomm\MoMo\Api\Data\RefundRequestInterface;
use Secomm\MoMo\Api\RefundRequestRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * momo:refund:list [--status] [--order] [--limit]
 */
class RefundListCommand extends Command
{
    public const NAME = 'momo:refund:list';

    /** @var RefundRequestRepositoryInterface */
    private $repository;

    /** @var State */
    private $state;

    public function __construct(
        RefundRequestRepositoryInterface $repository,
        State $state,
        ?string $name = null
    ) {
        $this->repository = $repository;
        $this->state = $state;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('List MoMo creditmemo refund requests (reconciliation evidence)');
        $this->addOption(
            'status',
            null,
            InputOption::VALUE_REQUIRED,
            'Filter by status (pending/success/failed/unknown)'
        );
        $this->addOption('order', null, InputOption::VALUE_REQUIRED, 'Filter by order increment id');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max rows (default 50)', '50');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $rows = $this->repository->getList(
            $input->getOption('status'),
            $input->getOption('order'),
            max(1, (int)$input->getOption('limit'))
        );
        if ($rows === []) {
            $output->writeln('<comment>No MoMo refund requests found.</comment>');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln(sprintf(
            '%-6s %-8s %-10s %-5s %-14s %-19s %-49s %-22s %-6s',
            'ID',
            'STATUS',
            'AMOUNT',
            'OPEN',
            'ORDER',
            'CREATED',
            'REQUEST_ID',
            'REASON',
            'CM_ID'
        ));
        foreach ($rows as $row) {
            $output->writeln(sprintf(
                '%-6d %-8s %-10d %-5s %-14s %-19s %-49s %-22s %-6s',
                $row->getEntityId(),
                $row->getStatus(),
                $row->getAmount(),
                $row->isOpen() ? 'yes' : 'no',
                substr($row->getOrderIncrementId(), 0, 14),
                substr((string)$row->getCreatedAt(), 0, 19),
                $row->getRequestId(),
                substr((string)$row->getClassificationReason(), 0, 22) ?: '-',
                (string)$row->getCreditmemoId() ?: '-'
            ));
        }

        return Cli::RETURN_SUCCESS;
    }
}
