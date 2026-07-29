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


namespace Mirasvit\SeoAudit\Check\Meta;


use Mirasvit\SeoAudit\Api\Data\CheckResultInterface;
use Mirasvit\SeoAudit\Api\Data\UrlInterface;
use Mirasvit\SeoAudit\Check\AbstractCheck;

class TitleDuplicate extends AbstractCheck
{
    public function getAllowedTypes(): array
    {
        return [UrlInterface::TYPE_PAGE];
    }

    public function isAllowedForExternal(): bool
    {
        return false;
    }

    public function getIdentifier(): string
    {
        return 'meta_title_duplicate';
    }

    public function getImportance(): int
    {
        return 1;
    }

    public function getCheckResult(UrlInterface $url): array
    {
        $ids = [];

        if ($url->getMetaTitle()) {
            $urls = $this->urlRepository->getCollection()
                ->addFieldToFilter(UrlInterface::TYPE, UrlInterface::TYPE_PAGE)
                ->addFieldToFilter(UrlInterface::STATUS, [UrlInterface::STATUS_CRAWLED, UrlInterface::STATUS_FINISHED])
                ->addFieldToFilter(UrlInterface::ID, ['neq' => $url->getId()])
                ->addFieldToFilter(UrlInterface::META_TITLE, $url->getMetaTitle())
                ->addFieldToSelect([UrlInterface::ID, UrlInterface::META_TITLE]);

            if ($url->getCanonical()) {
                $urls->addFieldToFilter(UrlInterface::CANONICAL, ['neq' => $url->getCanonical()]);
            }

            if ($url->getRobots()) {
                $urls->addFieldToFilter(UrlInterface::ROBOTS, ['nlike' => '%NOINDEX%']);
            }

            $urls->getSelect()->limit(20);

            /** @var UrlInterface $u */
            foreach ($urls as $u) {
                $ids[] = $u->getId();
            }
        }

        $value = [
            UrlInterface::META_TITLE => $url->getMetaTitle(),
            'ids'                    => $ids,
        ];

        return [
            CheckResultInterface::RESULT  => !$url->getMetaTitle() || count($ids) ? self::MIN_SCORE : self::MAX_SCORE,
            CheckResultInterface::VALUE   => $this->encodeValue($value),
            CheckResultInterface::MESSAGE => (string)__('Duplicate of meta title'),
        ];
    }

    public function getValueType(): string
    {
        return self::VALUE_TYPE_ARRAY;
    }

    public function getLabel(): string
    {
        return (string)__("Duplicate of meta title");
    }

    public function getGridColumnLabel(): string
    {
        return (string)__('URLs with the same meta titles');
    }

    public function getValueGridOutput(string $value): string
    {
        $value = $this->decodeValue($value);

        if (!is_array($value)) {
            return '';
        }

        if (!$value[UrlInterface::META_TITLE]) {
            return (string)__('The page doesn\'t have a meta title');
        } elseif (!count($value['ids'])) {
            return (string)__('No pages with the identical meta title found');
        }

        $output = '<table class="mst_seo_audit__links"><thead><tr><th class="data-grid-th">URL</th><th class="data-grid-th">Meta title</th></tr></thead><tbody>';

        $urls = $this->urlRepository->getCollection()
            ->addFieldToFilter(UrlInterface::ID, ['in' => $value['ids']]);

        /** @var UrlInterface $url */
        foreach ($urls as $url) {
            $output .= "<tr><td>{$this->prepareLinkHtml($url->getUrl())}</td><td>{$url->getMetaTitle()}</td></tr>";
        }

        $output .= '</tbody></table>';

        return $output;
    }
}
