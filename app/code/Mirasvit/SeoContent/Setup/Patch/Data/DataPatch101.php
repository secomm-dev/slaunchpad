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



namespace Mirasvit\SeoContent\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mirasvit\SeoContent\Api\Data\TemplateInterface;
use Mirasvit\SeoContent\Repository\TemplateRepository;

class DataPatch101 implements DataPatchInterface, PatchVersionInterface
{
    private $setup;

    private $templateRepository;

    private $serializer;

    public function __construct(
        ModuleDataSetupInterface $setup,
        TemplateRepository $templateRepository,
        Json $serializer
    ) {
        $this->setup              = $setup;
        $this->templateRepository = $templateRepository;
        $this->serializer         = $serializer;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getVersion(): string
    {
        return '1.0.1';
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->setup->startSetup();

        foreach ($this->templateRepository->getCollection() as $template) {
            $conditions = $template->getData(TemplateInterface::CONDITIONS_SERIALIZED);

            try {
                $decodedConditions = $this->serializer->unserialize($conditions);
            } catch (\Exception $e) {
                $decodedConditions = null;
            }
            if (!$decodedConditions) {
                $decodedConditions = [0 => $conditions];
            }
            $conditions = (string)$this->serializer->serialize($decodedConditions);

            $replaces = [
                "Mirasvit\\\\SeoContent\\\\Model\\\\Template\\\\Rule\\\\Condition\\\\Validate" =>
                    "Mirasvit\\\\SeoContent\\\\Model\\\\Template\\\\Rule\\\\Condition\\\\CategoryCondition",

                "Mirasvit\\\\Seo\\\\Model\\\\Template\\\\Rule" =>
                    "Mirasvit\\\\SeoContent\\\\Model\\\\Template\\\\Rule",
            ];

            foreach ($replaces as $from => $to) {
                $conditions = str_replace($from, $to, $conditions);
            }

            $template->setConditionsSerialized($conditions);
            $this->templateRepository->save($template);
        }

        $this->setup->endSetup();

        return $this;
    }
}
