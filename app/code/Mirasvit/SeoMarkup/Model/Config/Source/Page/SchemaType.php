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

namespace Mirasvit\SeoMarkup\Model\Config\Source\Page;

use Magento\Framework\Data\OptionSourceInterface;

class SchemaType implements OptionSourceInterface
{
    public const TYPE_WEBPAGE      = 'webpage';
    public const TYPE_ARTICLE      = 'article';
    public const TYPE_BLOG_POSTING = 'blogposting';
    public const TYPE_ABOUT_PAGE   = 'aboutpage';
    public const TYPE_CONTACT_PAGE = 'contactpage';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::TYPE_WEBPAGE,      'label' => __('WebPage')],
            ['value' => self::TYPE_ARTICLE,      'label' => __('Article')],
            ['value' => self::TYPE_BLOG_POSTING, 'label' => __('BlogPosting')],
            ['value' => self::TYPE_ABOUT_PAGE,   'label' => __('AboutPage')],
            ['value' => self::TYPE_CONTACT_PAGE, 'label' => __('ContactPage')],
        ];
    }
}
