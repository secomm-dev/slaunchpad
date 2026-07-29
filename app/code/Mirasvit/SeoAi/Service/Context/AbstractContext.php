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
use Mirasvit\SeoAi\Model\ConfigProvider;

class AbstractContext implements ContextInterface
{
    protected $storeManager;

    protected $configProvider;

    protected $objectManager;

    protected $moduleManager;

    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigProvider $configProvider,
        ObjectManagerInterface $objectManager,
        ModuleManager $moduleManager
    ) {
        $this->storeManager   = $storeManager;
        $this->configProvider = $configProvider;
        $this->objectManager  = $objectManager;
        $this->moduleManager  = $moduleManager;
    }

    public function getContext(?int $entityId = null, ?array $params= null): array
    {
        $store = $this->storeManager->getStore();

        $context = [];

        if (!$this->configProvider->isIncludeStoreData()) {
            return $context;
        }

        $context = ['store' => [
                [
                    'id'    => 'store.name',
                    'label' => 'Store Name',
                    'value' => $store->getName()
                ]
            ]
        ];

        if ($storeDescription = $this->configProvider->getAdditionalStoreData()) {
            $context['store'][] = [
                'id'    => 'store.description',
                'label' => 'Store Description',
                'value' => $storeDescription
            ];
        }

        return $context;
    }

    protected function clear(string $txt): string
    {
        $txt = $this->stripTags($txt);
        $txt = str_replace("\n", " ", $txt);
        $txt = str_replace("\r", " ", $txt);
        $txt = trim($txt);
        $txt = $this->maxWords($txt);

        return $txt;
    }

    protected function stripTags(string $html): string
    {
        $html = preg_replace('/<style>.*<\/style>/m', "", $html) ?? $html; //remove <style>..</style>
        $html = preg_replace('/{{widget.*}}/m', "", $html) ?? $html; //remove {{widget ... }}
        $html = strip_tags($html);
        $html = $this->replaceSpecialChars($html);
        $html = trim($html);
        return $html;
    }

    protected function replaceSpecialChars(string $html): string
    {
        foreach (get_html_translation_table(HTML_ENTITIES) as $char => $code) {
            $html = str_replace($code, $char, $html);
        }

        return $html;
    }

    protected function maxWords(string $str): string
    {
        $parts  = explode(' ', $str);
        $parts = array_slice($parts,0, 200);
        $str = implode(" ", $parts);
        return $str;
    }
}
