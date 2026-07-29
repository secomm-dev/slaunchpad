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

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Message\ManagerInterface;
use Mirasvit\SeoFilter\Api\Data\RewriteInterface;
use Mirasvit\SeoFilter\Repository\RewriteRepository;
use Mirasvit\SeoFilter\Service\LabelService;
use Mirasvit\SeoFilter\Service\RewriteService;
use Mirasvit\SeoFilter\Model\ConfigProvider;
use Mirasvit\SeoFilter\Api\Data\AttributeConfigInterface;
use Mirasvit\SeoFilter\Repository\AttributeConfigRepository;

/**
 * @see \Magento\Catalog\Model\ResourceModel\Eav\Attribute::save()
 * @SuppressWarnings(PHPMD)
 */
class SaveRewriteOnAttributeSavePlugin
{
    private $rewriteService;

    private $rewriteRepository;

    private $labelService;

    private $messageManager;

    private $configProvider;

    private $seoConfigRepository;

    public function __construct(
        RewriteService    $rewriteService,
        RewriteRepository $rewriteRepository,
        LabelService      $labelService,
        ConfigProvider    $configProvider,
        ManagerInterface  $messageManager,
        AttributeConfigRepository $seoConfigRepository
    ) {
        $this->rewriteService      = $rewriteService;
        $this->rewriteRepository   = $rewriteRepository;
        $this->labelService        = $labelService;
        $this->messageManager      = $messageManager;
        $this->configProvider      = $configProvider;
        $this->seoConfigRepository = $seoConfigRepository;
    }

    /**
     * @param Attribute $subject
     * @param \Closure  $proceed
     *
     * @return Attribute
     */
    public function aroundSave($subject, \Closure $proceed)
    {
        $attributeCode = (string)$subject->getAttributeCode();

        if (!$attributeCode || (int)$subject->getIsFilterable() === 0) {
            return $proceed();
        }

        $seoFilterData = $subject->getData('seo_filter');

        if (isset($seoFilterData[AttributeConfigInterface::ENABLE_SEO_URL])) {
            $enableSeoUrl = intval($seoFilterData[AttributeConfigInterface::ENABLE_SEO_URL]);

            $attributeConfig = $this->rewriteService->getAttributeConfig($attributeCode);

            if ($attributeConfig) {
                $attributeConfig->setAttributeStatus($enableSeoUrl);
                $this->seoConfigRepository->save($attributeConfig);
            }
        }

        if (isset($seoFilterData['attribute'])) {
            foreach ($seoFilterData['attribute'] as $storeId => $urlRewrite) {
                $storeId    = (int)$storeId;

                $urlRewrite = $this->labelService->excludeSpecialCharacters($urlRewrite);

                $oldAttributeRewrite = $urlRewrite = $urlRewrite ? (string)$urlRewrite : $attributeCode;

                $urlRewrite = $this->labelService->uniqueLabel($urlRewrite, $storeId, 0, $attributeCode);

                if ($oldAttributeRewrite !== $urlRewrite) {
                    $this->messageManager->addWarningMessage(
                        __(
                            'The rewrite "%1" already exists or is not available. New rewrite "%2" was generated.',
                            $oldAttributeRewrite,
                            $urlRewrite
                        )
                    );
                }

                $rewrite = $this->rewriteService->getAttributeRewrite(
                    $attributeCode,
                    $storeId,
                    false
                );

                if ($rewrite) {
                    $rewrite->setRewrite($urlRewrite);
                    $this->rewriteRepository->save($rewrite);
                }
            }
        }

        if (isset($seoFilterData['options'])) {
            foreach ($seoFilterData['options'] as $optionId => $item) {
                $optionId = (string)$optionId;
                foreach ($item as $storeId => $urlRewrite) {
                    $storeId    = (int)$storeId;
                    $urlRewrite = trim((string)$urlRewrite);

                    if (!$urlRewrite) {
                        continue;
                    }

                    $urlRewrite = $this->labelService->excludeSpecialCharacters($urlRewrite);

                    if (in_array($urlRewrite, $this->configProvider->getReservedAliases())) {
                        $oldRewrite = $urlRewrite;
                        $urlRewrite = $this->labelService->uniqueLabel($urlRewrite, $storeId);
                        $this->messageManager->addWarningMessage(
                            __(
                                'The rewrite "%1" already exists for one of the additional filter options. New rewrite "%2" was generated.',
                                $oldRewrite,
                                $urlRewrite
                            )
                        );
                    }

                    $existing = $rewrite = $this->rewriteRepository->getCollection()
                        ->addFieldToFilter(RewriteInterface::REWRITE, $urlRewrite)
                        ->addFieldToFilter(RewriteInterface::STORE_ID, $storeId);

                    if ($this->configProvider->getUrlFormat() == ConfigProvider::URL_FORMAT_ATTR_OPTIONS) {
                        $existing->addFieldToFilter(RewriteInterface::OPTION, ['notnull' => true])
                            ->addFieldToFilter(RewriteInterface::OPTION, ['neq' => $optionId])
                            ->addFieldToFilter(RewriteInterface::ATTRIBUTE_CODE, $attributeCode);
                    } else {
                        $existing->addFieldToFilter([RewriteInterface::OPTION, RewriteInterface::OPTION], [
                            ['neq' => $optionId],
                            ['null' => true],
                        ]);
                    }

                    if ($existing->getSize() || in_array($urlRewrite, $this->configProvider->getReservedAliases())) {
                        foreach ($existing as $existingRewrite) {
                            $oldRewrite = $urlRewrite;
                            $urlRewrite = $this->labelService->uniqueLabel($urlRewrite, $storeId);
                            $message = 'The rewrite "' . $oldRewrite . '" already exists for another option of "' 
                                . $existingRewrite->getAttributeCode() . '" attribute. New rewrite "' . $urlRewrite . '" was generated.';
                            $this->messageManager->addWarningMessage(__($message));
                        }
                    }

                    $rewrite = $this->rewriteService->getOptionRewrite(
                        $attributeCode,
                        $optionId,
                        $storeId,
                        false
                    );

                    if ($rewrite) {
                        $rewrite->setRewrite($urlRewrite);
                        $this->rewriteRepository->save($rewrite);
                    }

                }
            }

        }

        return $proceed();
    }
}
