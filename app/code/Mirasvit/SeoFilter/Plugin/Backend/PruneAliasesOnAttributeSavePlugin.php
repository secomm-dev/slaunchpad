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

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface;
use Mirasvit\SeoFilter\Service\ActualizeRewriteService;

/**
 * @see \Magento\Eav\Model\ResourceModel\Entity\Attribute::save()
 */
class PruneAliasesOnAttributeSavePlugin
{
    /** @var ActualizeRewriteService */
    private $actualizeRewriteService;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var int|null */
    private $productEntityTypeId;

    public function __construct(
        ActualizeRewriteService $actualizeRewriteService,
        ManagerInterface        $messageManager,
        ResourceConnection      $resourceConnection
    ) {
        $this->actualizeRewriteService = $actualizeRewriteService;
        $this->messageManager          = $messageManager;
        $this->resourceConnection      = $resourceConnection;
    }

    /**
     * @param Attribute $subject
     * @param Attribute $result
     * @param mixed     $object
     *
     * @return Attribute
     */
    public function afterSave(Attribute $subject, $result, $object)
    {
        if (!$object instanceof AbstractAttribute) {
            return $result;
        }

        $productEntityTypeId = $this->getProductEntityTypeId();
        if ($productEntityTypeId === 0 || (int)$object->getEntityTypeId() !== $productEntityTypeId) {
            return $result;
        }

        $attributeCode = (string)$object->getAttributeCode();

        if ($attributeCode !== '') {
            $pruned = $this->actualizeRewriteService->pruneOrphanedOptionAliases($attributeCode);
            if ($pruned > 0) {
                $this->messageManager->addSuccessMessage((string)__('%1 orphaned alias(es) removed.', $pruned));
            }
        }

        return $result;
    }

    private function getProductEntityTypeId(): int
    {
        if ($this->productEntityTypeId === null) {
            $connection = $this->resourceConnection->getConnection();
            $select     = $connection->select()
                ->from($this->resourceConnection->getTableName('eav_entity_type'), 'entity_type_id')
                ->where('entity_type_code = ?', ProductAttributeInterface::ENTITY_TYPE_CODE);

            $entityTypeId = (int)$connection->fetchOne($select);

            if ($entityTypeId > 0) {
                $this->productEntityTypeId = $entityTypeId;
            }

            return $entityTypeId;
        }

        return $this->productEntityTypeId;
    }
}
