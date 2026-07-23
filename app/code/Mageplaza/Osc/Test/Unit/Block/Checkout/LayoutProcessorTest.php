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

namespace Mageplaza\Osc\Test\Unit\Block\Checkout;

use Magento\Checkout\Block\Checkout\AttributeMerger;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\AttributeMetadataDataProvider;
use Magento\Customer\Model\Options;
use Magento\Customer\Model\ResourceModel\Form\Attribute\Collection;
use Magento\Eav\Model\Attribute;
use Magento\Framework\Phrase;
use Magento\Ui\Component\Form\AttributeMapper;
use Mageplaza\Osc\Block\Checkout\LayoutProcessor;
use Mageplaza\Osc\Helper\Address as OscHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class LayoutProcessorTest extends TestCase
{
    /**
     * @var OscHelper|MockObject
     */
    private $oscHelperMock;

    /**
     * @var AttributeMetadataDataProvider|MockObject
     */
    private $attributeMetadataDataProviderMock;

    /**
     * @var AttributeMapper|MockObject
     */
    private $attributeMapperMock;

    /**
     * @var AttributeMerger|MockObject
     */
    private $mergerMock;

    /**
     * @var Options|MockObject
     */
    private $optionsMock;

    /**
     * @var CheckoutSession|MockObject
     */
    private $checkoutSessionMock;

    /**
     * @var LayoutProcessor
     */
    private $layoutProcessorBlock;

    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->oscHelperMock = $this->getMockBuilder(OscHelper::class)
            ->disableOriginalConstructor()->getMock();
        $this->attributeMetadataDataProviderMock = $this->getMockBuilder(AttributeMetadataDataProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->attributeMapperMock = $this->getMockBuilder(AttributeMapper::class)
            ->disableOriginalConstructor()->getMock();
        $this->mergerMock = $this->getMockBuilder(AttributeMerger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->optionsMock = $this->getMockBuilder(Options::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->layoutProcessorBlock = new LayoutProcessor(
            $this->checkoutSessionMock,
            $this->oscHelperMock,
            $this->attributeMetadataDataProviderMock,
            $this->attributeMapperMock,
            $this->mergerMock,
            $this->optionsMock
        );
    }

    /**
     * @param string $methodName
     * @param array  $parameters
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function invokeMethod($methodName, $parameters = [])
    {
        $reflection = new ReflectionClass(LayoutProcessor::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->layoutProcessorBlock, $parameters);
    }

    /**
     * @return array
     */
    public function providerTestGetAddressAttributes()
    {
        return [
            [
                [
                    'custom' => [],
                    'default' => []
                ],
                'country_id',
                self::exactly(2),
                self::exactly(2),
                true,
                []
            ],
            [
                [
                    'custom' => [],
                    'default' => [
                        'country_id' => [
                            'label' => 'test'
                        ]
                    ]
                ],
                'country_id',
                self::once(),
                self::once(),
                false,
                [
                    'label' => 'test'
                ]
            ],
            [
                [
                    'custom' => [
                        'test' => ['label' => 'test']
                    ],
                    'default' => []
                ],
                'test',
                self::once(),
                self::once(),
                true,
                [
                    'label' => 'test'
                ]
            ],
        ];
    }

    /**
     * @param $result
     * @param string  $attributeCode
     * @param $attributeMapperExpects
     * @param $isUserDefinedExpect
     * @param boolean $isUserDefined
     * @param array   $attributeMap
     *
     * @dataProvider providerTestGetAddressAttributes
     * @throws       ReflectionException
     */
    public function testGetAddressAttributes(
        $result,
        $attributeCode,
        $attributeMapperExpects,
        $isUserDefinedExpect,
        $isUserDefined,
        $attributeMap
    ) {
        $fields = [
            'country_id' => [
            ]
        ];

        $attributeCollectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->attributeMetadataDataProviderMock->expects($this->exactly(2))
            ->method('loadAttributesCollection')
            ->withConsecutive(
                ['customer_address', 'onestepcheckout_index_index'],
                ['customer_address', 'customer_register_address']
            )->willReturn($attributeCollectionMock);
        $attributeMock = $this->getMockBuilder(\Magento\Customer\Model\Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $attributeCollectionMock->expects($this->exactly(2))->method('getItems')->willReturn([$attributeMock]);

        $attributeMock->expects($this->atLeastOnce())->method('getAttributeCode')->willReturn($attributeCode);
        $this->attributeMapperMock->expects($attributeMapperExpects)
            ->method('map')
            ->with($attributeMock)->willReturn($attributeMap);
        $attributeMock->expects($isUserDefinedExpect)->method('getIsUserDefined')->willReturn($isUserDefined);

        $this->assertEquals($result, $this->invokeMethod('getAddressAttributes', [$fields]));
    }

    /**
     * @return array
     */
    public function providerTestAddCustomerAttribute()
    {
        $fields = [
            'postcode' => [
                'component' => 'Magento_Ui/js/form/element/post-code',
                'dataScope' => 'shippingAddress.postcode',
                'provider' => 'checkoutProvider',
                'sortOrder' => '110',
                'validation' => [
                    'required-entry' => true,
                ],
            ]
        ];

        $fieldsWithoutLabel = $fields;
        $fieldsWithoutLabel['postcode']['dataScope'] = 'billingAddress.postcode';
        $fields['postcode']['label'] = 'Zip/Postal Code';

        return [
            [
                [
                    'postcode' => [
                        'component' => 'Magento_Ui/js/form/element/post-code',
                        'dataScope' => 'shippingAddress.postcode',
                        'label' => new Phrase('Zip/Postal Code'),
                        'provider' => 'checkoutProvider',
                        'sortOrder' => '110',
                        'validation' => [
                            'required-entry' => true,
                        ],
                    ],
                    'my_attribute' => [
                        'component' => 'Magento_Ui/js/form/element/abstract',
                        'label' => 'My Attribute',
                        'provider' => 'checkoutProvider',
                        'sortOrder' => '110',
                        'validation' => [
                            'required-entry' => true,
                        ]
                    ]
                ],
                $fields,
                true,
                'shippingAddress'
            ],
            [
                [
                    'postcode' => [
                        'component' => 'Magento_Ui/js/form/element/post-code',
                        'dataScope' => 'shippingAddress.postcode',
                        'label' => new Phrase('Zip/Postal Code'),
                        'provider' => 'checkoutProvider',
                        'sortOrder' => '110',
                        'validation' => [
                            'required-entry' => true,
                        ],
                    ],
                ],
                $fields,
                false,
                'shippingAddress'
            ],
            [
                [
                    'postcode' => [
                        'component' => 'Magento_Ui/js/form/element/post-code',
                        'dataScope' => 'billingAddress.postcode',
                        'provider' => 'checkoutProvider',
                        'sortOrder' => '110',
                        'validation' => [
                            'required-entry' => true,
                        ],
                    ],
                ],
                $fieldsWithoutLabel,
                false,
                'billingAddress'
            ]
        ];
    }

    /**
     * @param array   $result
     * @param array   $fields
     * @param boolean $isCustomerAttribute
     * @param string  $type
     *
     * @dataProvider providerTestAddCustomerAttribute
     *
     * @throws ReflectionException
     */
    public function testAddCustomerAttribute($result, $fields, $isCustomerAttribute, $type)
    {
        $attributeCollectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $attributeMock = $this->getMockBuilder(Attribute::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->attributeMetadataDataProviderMock->expects($this->once())
            ->method('loadAttributesCollection')
            ->with('customer', 'customer_account_create')
            ->willReturn($attributeCollectionMock);
        $attributeCollectionMock->expects($this->once())->method('getItems')->willReturn([$attributeMock]);
        $this->oscHelperMock->expects($this->once())->method('isCustomerAttributeVisible')
            ->with($attributeMock)
            ->willReturn($isCustomerAttribute);

        if ($isCustomerAttribute) {
            $attributeMock->expects($this->once())->method('getAttributeCode')->willReturn('my_attribute');
            $myAttribute = [
                'component' => 'Magento_Ui/js/form/element/abstract',
                'label' => 'My Attribute',
                'provider' => 'checkoutProvider',
                'sortOrder' => '110',
                'validation' => [
                    'required-entry' => true,
                ],
            ];
            $addressElements = [];
            $addressElements['my_attribute'] = $myAttribute;
            $this->attributeMapperMock->expects($this->once())->method('map')->willReturn($myAttribute);

            $merger = $fields;
            $merger['my_attribute'] = $myAttribute;
            $this->mergerMock->expects($this->once())
                ->method('merge')
                ->with($addressElements, 'checkoutProvider', $type . '.custom_attributes', $fields)
                ->willReturn($merger);
        }

        $this->invokeMethod('addCustomerAttribute', [&$fields, $type]);

        $this->assertEquals($result, $fields);
    }
}
