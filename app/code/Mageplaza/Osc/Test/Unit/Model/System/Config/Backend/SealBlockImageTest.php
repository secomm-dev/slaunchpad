<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category  Mageplaza
 * @package   Mageplaza_Osc
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Osc\Test\Unit\Model\System\Config\Backend;

use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Mageplaza\Osc\Model\System\Config\Backend\SealBlockImage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SealBlockImageTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $mediaDirectory;

    /**
     * @var SealBlockImage
     */
    private $sealBlockImage;

    public function setUp(): void
    {
        $objectManagerHelper = new ObjectManager($this);

        $this->mediaDirectory = $this->getMockForAbstractClass(WriteInterface::class);
        $this->sealBlockImage = $objectManagerHelper->getObject(
            SealBlockImage::class,
            [
                '_mediaDirectory' => $this->mediaDirectory
            ]
        );
    }

    public function testGetUploadDir()
    {
        $this->mediaDirectory->expects($this->once())->method('getAbsolutePath')->with('mageplaza/osc/seal///');
        $helperDataObject = new ReflectionClass(SealBlockImage::class);
        $method = $helperDataObject->getMethod('_getUploadDir');
        $method->setAccessible(true);

        $method->invokeArgs($this->sealBlockImage, []);
    }
}
