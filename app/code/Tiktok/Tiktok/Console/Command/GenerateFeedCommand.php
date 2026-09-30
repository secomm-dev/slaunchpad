<?php
declare(strict_types=1);

namespace Tiktok\Tiktok\Console\Command;

use Exception;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tiktok\Tiktok\Model\Catalog\Export\CsvExport;

class GenerateFeedCommand extends Command
{
    /**
     * File Path Option
     */
    public const FILE_PATH_OPTION = 'file-path';

    /**
     * Website ID Option
     */
    public const WEBSITE_ID= 'website-id';

    /**
     * @var \Tiktok\Tiktok\Model\Catalog\Export\CsvExport
     */
    protected CsvExport $feedGenerator;

    /**
     * @var \Magento\Framework\App\State
     */
    private State $appState;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Init dependencies
     *
     * @param \Tiktok\Tiktok\Model\Catalog\Export\CsvExport $feedGenerator
     * @param \Magento\Framework\App\State $appState
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        CsvExport $feedGenerator,
        State $appState,
        LoggerInterface $logger
    ) {
        $this->feedGenerator = $feedGenerator;
        $this->appState = $appState;
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('tiktok:generate:feed')->setDescription('Generate custom product feed')
            ->addOption(
                self::WEBSITE_ID,
                'w',
                InputOption::VALUE_REQUIRED,
                'Website to export',
                '/pub/export'
            )
            ->addOption(
                self::FILE_PATH_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'File path to save the export',
                '/pub/export'
            );
        parent::configure();
    }

    /**
     * Execute command
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getOption(self::FILE_PATH_OPTION);
        $website = $input->getOption(self::WEBSITE_ID);

        try {
            $this->appState->setAreaCode('adminhtml');
            $this->feedGenerator->export($website);
            $output->writeln("<info>Feed generated successfully at {$filePath}</info>");
            return Cli::RETURN_SUCCESS;
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
            $output->writeln("<error>Error generating feed: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        } catch (Exception $e) {
            $this->logger->critical($e);
            $output->writeln("<error>An unexpected error occurred: {$e->getMessage()}</error>");
            return Cli::RETURN_FAILURE;
        }
    }
}
