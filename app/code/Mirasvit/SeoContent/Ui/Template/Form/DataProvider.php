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



namespace Mirasvit\SeoContent\Ui\Template\Form;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Api\Repository\TemplateRepositoryInterface;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * DataProvider constructor.
     * @param TemplateRepositoryInterface $templateRepository
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        TemplateRepositoryInterface $templateRepository,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        $name,
        $primaryFieldName,
        $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $this->collection   = $templateRepository->getCollection();
        $this->storeManager = $storeManager;
        $this->request = $request;

        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        $result = [];

        foreach ($this->collection as $item) {
            $result[$item->getId()] = [
                TemplateInterface::ID         => $item->getId(),
                TemplateInterface::RULE_TYPE  => (string)$item->getRuleType(),
                TemplateInterface::NAME       => $item->getName(),
                TemplateInterface::IS_ACTIVE  => $item->getIsActive() ? '1' : '0',
                TemplateInterface::SORT_ORDER => $item->getSortOrder(),
                TemplateInterface::STORE_IDS  => $item->getStoreIds(),

                TemplateInterface::TITLE            => $item->getTitle(),
                TemplateInterface::META_TITLE       => $item->getMetaTitle(),
                TemplateInterface::META_KEYWORDS    => $item->getMetaKeywords(),
                TemplateInterface::META_DESCRIPTION => $item->getMetaDescription(),

                TemplateInterface::DESCRIPTION          => $item->getDescription(),
                TemplateInterface::SHORT_DESCRIPTION    => $item->getShortDescription(),
                TemplateInterface::FULL_DESCRIPTION     => $item->getFullDescription(),
                TemplateInterface::DESCRIPTION_POSITION => $item->getDescriptionPosition(),
                TemplateInterface::DESCRIPTION_TEMPLATE => $item->getDescriptionTemplate(),
                TemplateInterface::CATEGORY_DESCRIPTION => $item->getCategoryDescription(),
                TemplateInterface::BRAND_DESCRIPTION    => $item->getBrandDescription(),

                TemplateInterface::STOP_RULE_PROCESSING       => $item->getStopRulesProcessing() ? '1' : '0',
                TemplateInterface::APPLY_FOR_CHILD_CATEGORIES => $item->getApplyForChildCategories() ? '1' : '0',
                TemplateInterface::APPLY_FOR_HOMEPAGE         => $item->getApplyForHomepage() ? '1' : '0',
                TemplateInterface::APPLY_FOR_ALL_BRANDS_PAGE  => $item->getApplyForAllBrandsPage() ? '1' : '0',
                TemplateInterface::APPLY_FOR_LANDING_PAGES    => $item->getApplyForLandingPages() ? '1' : '0',
            ];

            if ($item->getCategoryImage()) {
                $url = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/category/'
                    . $item->getCategoryImage();

                $result[$item->getId()][TemplateInterface::CATEGORY_IMAGE] = [
                    [
                        'name' => $item->getCategoryImage(),
                        'url'  => $url,
                        'size' => '100',
                        'type' => 'image',
                    ],
                ];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $id = $this->request->getParam('template_id');

        if (isset($id)) {
            $meta['general']['children']['rule_type']['arguments']['data']['config']['disabled'] = 1;
            $meta['conditions']['arguments']['data']['config']['visible'] = 1;
        } else {
            $meta['general']['children']['rule_type']['arguments']['data']['config']['disabled'] = 0;
            $meta['conditions']['arguments']['data']['config']['visible'] = 0;
        }

        return $meta;
    }
    //
    //    /**
    //     * @param array $data
    //     * @return array
    //     */
    //    private function prepareImageData($data, $imageKey)
    //    {
    //        if (isset($data[$imageKey])) {
    //            $imageName = $data[$imageKey];
    //            unset($data[$imageKey]);
    //            if ($this->imageFile->isExist($imageName)) {
    //                $stat = $this->imageFile->getStat($imageName);
    //                $data[$imageKey] = [
    //                    [
    //                        'name' => $imageName,
    //                        'url'  => $this->imageService->getCategoryImageUrl($imageName),
    //                        'size' => isset($stat) ? $stat['size'] : 0,
    //                        'type' => $this->imageFile->getMimeType($imageName),
    //                    ],
    //                ];
    //            }
    //        }
    //        return $data;
    //    }
}
