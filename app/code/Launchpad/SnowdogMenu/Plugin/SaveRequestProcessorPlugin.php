<?php
/**
 * Launchpad Snowdog Menu — banner persistence bridge (TASK-08343C).
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Plugin;

use Launchpad\SnowdogMenu\Model\NodeBannerManagement;
use Snowdog\Menu\Api\Data\NodeInterface;
use Snowdog\Menu\Service\Menu\SaveRequestProcessor;

/**
 * Bridges the editor's banner fields (they travel inside the serialized node
 * payload but the vendor processor only sets its own known setters) to the
 * companion storage:
 *
 * - after processNodeObject: stage the raw payload values on the node object
 *   (by this point the object carries its persisted node id — new nodes are
 *   created in the loop right before this call); the values are persisted by
 *   NodeRepositorySavePlugin's save-after hook, which fires immediately after
 *   processNodeObject in the save loop;
 * - after saveData: ONE cache invalidation if any banner row changed.
 *
 * Consistency note: the vendor saveData does not wrap the whole tree in a
 * transaction — a mid-save failure can leave a partially synced tree. Banner
 * rows are keyed by node id, so a re-run of the same save converges.
 */
class SaveRequestProcessorPlugin
{
    public const STAGE_KEY = 'launchpad_banner_staged';
    public const PAYLOAD_CONTENT = 'banner_content';
    public const PAYLOAD_MOBILE = 'show_banner_content_mobile';

    public function __construct(
        private readonly NodeBannerManagement $nodeBannerManagement
    ) {
    }

    public function afterProcessNodeObject(
        SaveRequestProcessor $subject,
        $result,
        NodeInterface $nodeObject,
        array $nodeData
    ) {
        if (array_key_exists(self::PAYLOAD_CONTENT, $nodeData)
            || array_key_exists(self::PAYLOAD_MOBILE, $nodeData)
        ) {
            $content = $nodeData[self::PAYLOAD_CONTENT] ?? null;
            // an explicitly emptied editor ('' / empty WYSIWYG shell) must keep
            // the row so the mobile flag can point mobile at the CMS fallback
            $nodeObject->setData(self::STAGE_KEY, [
                'content' => $content === null ? null : (string) $content,
                'mobile' => (bool) ($nodeData[self::PAYLOAD_MOBILE] ?? false),
            ]);
        }

        return $result;
    }

    public function afterSaveData(SaveRequestProcessor $subject, $result, $menu, array $nodes = [])
    {
        $this->nodeBannerManagement->invalidateIfDirty();

        return $result;
    }
}
