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



namespace Mirasvit\SeoToolbar\DataProvider\Criteria;

use Mirasvit\SeoToolbar\Api\Data\DataProviderItemInterface;

class MetaKeywordsCriteria extends AbstractCriteria
{
    const LABEL = 'Meta keywords';

    /**
     * @param string $content
     * @return array
     */
    public function handle($content)
    {
        $value = $this->getMetaTag($content, 'keywords');

        $len = mb_strlen($value ?? '');
        $words = count(explode(',', $value ?? ''));

        if ($len < 160) {
            return $this->getItem(
                self::LABEL,
                DataProviderItemInterface::STATUS_WARNING,
                __('%1 characters — not good. Try to enlarge it to 160 characters.', $len),
                $value
            );
        }

        if ($len > 300) {
            return $this->getItem(
                self::LABEL,
                DataProviderItemInterface::STATUS_WARNING,
                __('%1 characters — not good. Try to minimize it to 300 characters.', $len),
                $value
            );
        }

        return $this->getItem(
            self::LABEL,
            DataProviderItemInterface::STATUS_SUCCESS,
            __('%1 characters, %2 words.', $len, $words),
            $value
        );
    }
}
