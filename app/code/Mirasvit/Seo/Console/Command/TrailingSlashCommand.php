<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-seo
 * @version   2.12.8
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\Seo\Console\Command;

use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\Seo\Service\TrailingSlashService;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TrailingSlashCommand extends Command
{
    private $trailingSlashService;

    private $storeManager;

    public function __construct(
        TrailingSlashService  $trailingSlashService,
        StoreManagerInterface $storeManager
    ) {
        $this->trailingSlashService = $trailingSlashService;
        $this->storeManager         = $storeManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('mirasvit:seo:trailing-slash')
            ->setDescription('Trailing slash');

        $this->addOption(
            'apply',
            null,
            null,
            'Apply the trailing slash setting'
        );

        $this->addOption(
            'store-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Apply the trailing slash setting only for a specific store'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('apply')) {
            $storeId = $input->getOption('store-id');

            $storeIds = $storeId ? [$storeId] : array_keys($this->storeManager->getStores());

            foreach ($storeIds as $storeId) {
                $this->trailingSlashService->processUrlRewrites((int)$storeId);
            }

            $output->writeln('Done!');
        } else {
            $help = new HelpCommand();
            $help->setCommand($this);

            $output->writeln('Common Usage:');
            $output->writeln('  mirasvit:seo:trailing-slash --apply               This will apply the trailing slash setting for all stores');
            $output->writeln('  mirasvit:seo:trailing-slash --apply --store-id=1  This will apply the trailing slash setting only for a specific store');
            $output->writeln('');

            $help->run($input, $output);
        }

        return 0;
    }
}
