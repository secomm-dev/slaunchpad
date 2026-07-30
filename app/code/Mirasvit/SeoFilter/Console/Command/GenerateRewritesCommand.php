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
 * @package   mirasvit/module-seo-filter
 * @version   1.3.64
 * @copyright Copyright (C) 2026 Mirasvit (https://mirasvit.com/)
 */



declare(strict_types=1);

namespace Mirasvit\SeoFilter\Console\Command;


use Magento\Catalog\Model\Layer\Category\FilterableAttributeList;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Mirasvit\SeoFilter\Service\ActualizeRewriteService;
use Mirasvit\SeoFilter\Service\RewriteService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateRewritesCommand extends Command
{
    private $appState;

    private $storeManager;

    private $filterableAttributeList;

    private $rewriteService;

    private $rewriteRepository;

    private $actualizeRewriteService;

    public function __construct(
        ObjectManagerInterface $objectManager,
        State $appState,
        StoreManagerInterface $storeManager,
        FilterableAttributeList $filterableAttributeList,
        RewriteService $rewriteService,
        RewriteRepository $rewriteRepository,
        ActualizeRewriteService $actualizeRewriteService
    ) {
        $this->appState                = $appState;
        $this->storeManager            = $storeManager;
        $this->filterableAttributeList = $filterableAttributeList;
        $this->rewriteService          = $rewriteService;
        $this->rewriteRepository       = $rewriteRepository;
        $this->actualizeRewriteService = $actualizeRewriteService;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('mirasvit:seo-filter:rewrites')
            ->setDescription('Generate SEO-friendly rewrites for filter options');

        $this->addOption('generate', null, null, 'Generate Rewrites');
        $this->addOption('remove', null, null, 'Remove All Rewrites');
        $this->addOption('actualize', null, null, 'Remove Unused Rewrites');

        parent::configure();
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode('frontend');
        } catch (\Exception $e) {
        }

        if ($input->getOption('generate')) {
            $output->writeln('Generating filters rewrites for attributes:');

            foreach ($this->storeManager->getStores() as $store) {
                $this->storeManager->setCurrentStore($store);

                $attributes = $this->filterableAttributeList->getList();

                /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute */
                foreach ($attributes as $attribute) {
                    $output->writeln($attribute->getStoreLabel($store) . ' (' . $attribute->getAttributeCode() . ')...');

                    $attributeCode = $attribute->getAttributeCode();
                    $this->rewriteService->getAttributeRewrite($attributeCode, (int)$store->getId(), false);

                    if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'])) {
                        continue;
                    }

                    foreach ((array)$attribute->getOptions() as $option) {
                        $optionValue = (string)$option->getValue();

                        if ($optionValue === '') {
                            continue;
                        }

                        if (preg_match('/[;\'"()\\s]|--/', $optionValue)) {
                            $output->writeln(sprintf(
                                '<comment>Skipped potentially unsafe option value "%s" for attribute "%s"</comment>',
                                $optionValue,
                                $attributeCode
                            ));
                            continue;
                        }

                        $this->rewriteService->getOptionRewrite(
                            $attributeCode,
                            $optionValue,
                            (int)$store->getId(),
                            false
                        );
                    }
                }
            }

            $output->writeln('Done!');

            return 0;
        }

        if ($input->getOption('remove')) {
            $output->writeln('Removing existing filters rewrites...');
            $resource = $this->rewriteRepository->create()->getResource();
            $resource->getConnection()->query('TRUNCATE TABLE ' . $resource->getTable(RewriteInterface::TABLE_NAME));
            $output->writeln('Done!');

            return 0;
        }

        if ($input->getOption('actualize')) {
            $output->writeln('Removing filters rewrites for non-existent options');
            $deleted = $this->actualizeRewriteService->pruneOrphanedOptionAliases();
            $output->writeln($deleted
                ? $deleted . ' unused option aliases were deleted'
                : 'No unused option aliases to remove');

            $output->writeln('Removing filters rewrites for non-filterable attributes');
            $deleted = $this->actualizeRewriteService->pruneNonFilterableAttributeAliases();
            $output->writeln($deleted
                ? $deleted . ' aliases for non-filterable attributes were deleted'
                : 'No non-filterable attribute aliases to remove');

            $output->writeln('Removing filters rewrites for non-existent grouped options');
            $deleted = $this->actualizeRewriteService->pruneOrphanedGroupedOptionAliases();
            $output->writeln($deleted
                ? $deleted . ' aliases for non-existent grouped options were deleted'
                : 'No non-existent grouped option aliases to remove');

            $output->writeln('Done!');
            return 0;
        }

        $help = new HelpCommand();
        $help->setCommand($this);

        $help->run($input, $output);

        return 0;
    }
}
