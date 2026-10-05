<?php
/**
 * Launchpad Snowdog Menu — node banner model (TASK-08343C / FEAT-ZNJ4KF).
 */

declare(strict_types=1);

namespace Launchpad\SnowdogMenu\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Companion record for a Snowdog node: optional WYSIWYG banner content and
 * the mobile-visibility flag for that content (the node image is separate).
 */
class NodeBanner extends AbstractModel
{
    public const NODE_ID = 'node_id';
    public const BANNER_CONTENT = 'banner_content';
    public const SHOW_ON_MOBILE = 'show_banner_content_mobile';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\NodeBanner::class);
    }

    public function getBannerContent(): ?string
    {
        $content = $this->getData(self::BANNER_CONTENT);

        return $content === null ? null : (string) $content;
    }

    public function isShownOnMobile(): bool
    {
        return (int) $this->getData(self::SHOW_ON_MOBILE) === 1;
    }
}
