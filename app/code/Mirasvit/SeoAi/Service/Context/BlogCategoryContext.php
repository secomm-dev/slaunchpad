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


declare(strict_types=1);


namespace Mirasvit\SeoAi\Service\Context;


use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class BlogCategoryContext extends AbstractContext
{
    public function getContext(?int $entityId = null, ?array $params = null): array
    {
        $context = parent::getContext($entityId, $params);

        if ($this->moduleManager->isEnabled('Mirasvit_BlogMx') && $entityId !== null) {
            $context['blog_category'] = $this->getMirasvitBlogCategoryContext($entityId);
        }

        return $context;
    }

    private function getMirasvitBlogCategoryContext(int $entityId): array
    {
        $repository = $this->objectManager->get('Mirasvit\BlogMx\Repository\CategoryRepository');
        $category   = $repository->get($entityId);

        $data = [
            [
                'id'    => 'category.title',
                'label' => 'Title',
                'value' => $this->stripTags($category->getName()),
            ]
        ];

        return $data;
    }
}
