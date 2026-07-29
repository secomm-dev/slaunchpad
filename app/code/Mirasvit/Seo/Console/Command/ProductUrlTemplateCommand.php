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

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\State;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollection;
use Mirasvit\Seo\Service\UrlTemplate\ProductUrlRegenerateServiceFactory;
use Mirasvit\Seo\Service\UrlTemplate\ProductUrlTemplateServiceFactory;
use PhpCsFixer\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProductUrlTemplateCommand extends Command
{
    private $appState;

    private $urlRewriteCollection;

    private $productUrlTemplateServiceFactory;

    private $productUrlRegenerateServiceFactory;

    private $productCollectionFactory;

    public function __construct(
        State                              $appState,
        ProductUrlTemplateServiceFactory   $productUrlTemplateServiceFactory,
        ProductUrlRegenerateServiceFactory $productUrlRegenerateServiceFactory,
        UrlRewriteCollection               $urlRewriteCollection,
        ProductCollectionFactory           $productCollectionFactory
    ) {
        $this->appState                           = $appState;
        $this->productUrlTemplateServiceFactory   = $productUrlTemplateServiceFactory;
        $this->productUrlRegenerateServiceFactory = $productUrlRegenerateServiceFactory;
        $this->urlRewriteCollection               = $urlRewriteCollection;
        $this->productCollectionFactory           = $productCollectionFactory;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('mirasvit:seo:product-url-template')
            ->setDescription('Product Url Key Generator');

        $this->addOption(
            'apply',
            null,
            null,
            'Apply product url key template'
        );

        $this->addOption(
            'restore',
            null,
            null,
            'Restore product url key template using [product_name]'
        );

        $this->addOption(
            'store-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Apply or restore product url key only for a specific store'
        );

        $this->addOption(
            'product-id',
            null,
            InputOption::VALUE_REQUIRED,
            'Apply or restore product url key only for a specific product'
        );

        $this->addOption(
            'dry-run',
            null,
            null,
            'If you want to run command without actually changing urls, you can use this option.
             This will simulate the applying and show you what would happen'
        );

        $this->addOption(
            'regenerate',
            null,
            null,
            'Regenerate urls in table url_rewrite. Useful if database was corrupted'
        );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->appState->isAreaCodeEmulated()) {
            try {
                $this->appState->setAreaCode('frontend');
            } catch (\Exception $e) {}
        }

        if ($input->getOption('apply') || $input->getOption('restore')) {
            $urlTemplateService = $this->productUrlTemplateServiceFactory->create();

            $urlKeyTemplates = $urlTemplateService->getUrlKeyTemplates();

            if ($input->getOption('restore')) {
                foreach (array_keys($urlKeyTemplates) as $storeId) {
                    $urlKeyTemplates[$storeId] = '[product_name]';
                }
            }

            $rewriteCollection = $this->urlRewriteCollection
                ->addFieldToFilter('entity_type', 'product')
                ->addFieldToFilter('redirect_type', 0)
                ->addFieldToFilter('metadata', ['null' => true]);

            $inputStoreId   = $input->getOption('store-id');
            $inputProductId = $input->getOption('product-id');

            if ($inputStoreId) {
                $rewriteCollection->addFieldToFilter('store_id', $inputStoreId);
            } else {
                $productCollection = $this->productCollectionFactory->create();
                $productCollection->setStoreId(Store::DEFAULT_STORE_ID)
                    ->setFlag('has_stock_status_filter');

                if ($inputProductId) {
                    $productCollection->addIdFilter($inputProductId);
                }

                foreach ($urlTemplateService->processProductCollection(
                    $productCollection,
                    $urlKeyTemplates,
                    $input->getOption('dry-run')
                ) as $result) {
                    $output->writeln($result);
                }
            }

            if ($inputProductId) {
                $rewriteCollection->addFieldToFilter('entity_id', $inputProductId);
            }

            foreach ($urlTemplateService->processUrlRewriteCollection(
                $rewriteCollection,
                $urlKeyTemplates,
                $input->getOption('dry-run'),
                $inputStoreId,
                $inputProductId
            ) as $result) {
                $output->writeln($result);
            }

            if (!$input->getOption('dry-run')) {
                $output->writeln('Please clear magento cache');
            }
        } elseif ($input->getOption('regenerate')) {
            $urlRegenerateService = $this->productUrlRegenerateServiceFactory->create();

            foreach ($urlRegenerateService->restore() as $result) {
                $output->writeln($result);
            }
        } else {
            $help = new HelpCommand();
            $help->setCommand($this);

            $output->writeln('Common Usage:');
            $output->writeln('  mirasvit:seo:product-url-template --apply --dry-run    This will simulate the applying');
            $output->writeln('  mirasvit:seo:product-url-template --restore --dry-run  This will simulate the restoring for original values');
            $output->writeln('');

            $help->run($input, $output);
        }

        return 0;
    }
}
