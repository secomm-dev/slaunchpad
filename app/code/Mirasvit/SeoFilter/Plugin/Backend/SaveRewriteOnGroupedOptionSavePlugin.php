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

namespace Mirasvit\SeoFilter\Plugin\Backend;

use Magento\Framework\Message\ManagerInterface;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Mirasvit\SeoFilter\Service\LabelService;
use Mirasvit\SeoFilter\Service\RewriteService;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\LayeredNavigation\Repository\GroupRepository;
use Mirasvit\LayeredNavigation\Api\Data\GroupInterface;
use Magento\Framework\App\RequestInterface;

/**
 * @see \Mirasvit\LayeredNavigation\Repository\GroupRepository::save()
 */
class SaveRewriteOnGroupedOptionSavePlugin
{
    private $rewriteService;

    private $rewriteRepository;

    private $labelService;

    private $messageManager;

    private $configProvider;

    private $request;

    public function __construct(
        RewriteService    $rewriteService,
        RewriteRepository $rewriteRepository,
        LabelService      $labelService,
        ConfigProvider    $configProvider,
        ManagerInterface  $messageManager,
        RequestInterface  $request
    ) {
        $this->rewriteService      = $rewriteService;
        $this->rewriteRepository   = $rewriteRepository;
        $this->labelService        = $labelService;
        $this->messageManager      = $messageManager;
        $this->configProvider      = $configProvider;
        $this->request             = $request;
    }

    public function afterSave(GroupRepository $subject, GroupInterface $result, GroupInterface $group): GroupInterface
    {
        $attributeCode = $result->getAttributeCode();

        $optionCode = $result->getCode();

        $aliases = $this->request->getParam('aliases', []);

        if (empty($aliases)) {
            return $result;
        }
        
        foreach ($aliases as $storeId => $urlRewrite) {
            $storeId    = (int)$storeId;
            $urlRewrite = trim((string)$urlRewrite);

            if (!$urlRewrite) {
                continue;
            }

            $rewrite = $this->rewriteService->getOptionRewrite(
                $attributeCode,
                $optionCode,
                $storeId,
                false
            );

            if (!$rewrite) {
                continue;
            }

            $urlRewrite = $this->labelService->excludeSpecialCharacters($urlRewrite);
            $currentAlias = $rewrite->getRewrite();

            if ($currentAlias === $urlRewrite) {
                continue;
            }

            $existing = $this->rewriteRepository->getCollection()
                ->addFieldToFilter(RewriteInterface::REWRITE, $urlRewrite)
                ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId);

            if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
                $existing->addFieldToFilter(RewriteInterface::OPTION, ['notnull' => true])
                    ->addFieldToFilter(RewriteInterface::OPTION, ['neq' => $optionCode])
                    ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode);
            } else {
                $existing->addFieldToFilter([RewriteInterface::OPTION, RewriteInterface::OPTION], [
                    ['neq' => $optionCode],
                    ['null' => true],
                ]);
            }

            if ($existing->getSize()) {
                foreach ($existing as $existingRewrite) {
                    $oldRewrite = $urlRewrite;
                    $urlRewrite = $this->labelService->uniqueLabel($urlRewrite, $storeId);
                    $message = 'The rewrite "' . $oldRewrite . '" already exists for another option of "'
                        . $existingRewrite->getAttributeCode() . '" attribute. New rewrite "' . $urlRewrite . '" was generated.';
                    $this->messageManager->addWarningMessage(__($message));
                }
            }

            $rewrite->setRewrite($urlRewrite);
            $this->rewriteRepository->save($rewrite);
        }

        return $result;
    }
}
