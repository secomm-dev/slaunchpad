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

namespace Mageplaza\Osc\Test\Unit\Model;

use Mageplaza\Osc\Helper\Data as OscHelper;
use Mageplaza\Osc\Model\AgreementsValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AgreementsValidatorTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var AgreementsValidator
     */
    private $model;

    protected function setUp(): void
    {
        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->model = new AgreementsValidator($this->oscHelperMock);
    }

    public function testIsValid()
    {
        $this->oscHelperMock->expects($this->once())->method('isEnabledTOC')->willReturn(true);

        $this->assertTrue($this->model->isValid());
    }
}
