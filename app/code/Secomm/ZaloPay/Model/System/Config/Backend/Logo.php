<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2024. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Secomm\ZaloPay\Model\System\Config\Backend;

use Magento\Config\Model\Config\Backend\Image;

/**
 * Uploaded checkout logo backend model (TASK-MCHN2T): the core image backend
 * (png/jpg/jpeg/gif) with a ZaloPay allow-list - PNG/JPG/JPEG/WEBP only.
 * SVG (and other vector formats) is rejected at upload time (issue #8 AC10:
 * SVG out of scope). Validation uses the allow-list semantics of
 * \Magento\MediaStorage\Model\File\Uploader.
 */
class Logo extends Image
{
    /**
     * Allow-list for the checkout logo upload. Replaces the core backend's
     * gif support with webp (AC10: PNG/JPG/JPEG/WEBP only).
     *
     * @return string[]
     */
    protected function _getAllowedExtensions(): array
    {
        return ['png', 'jpg', 'jpeg', 'webp'];
    }
}
