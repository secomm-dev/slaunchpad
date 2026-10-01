<?php
/*
 * @author Secomm Team
 * @copyright Copyright (c) 2026. Secomm All rights reserved (https://www.secomm.vn)
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Secomm\ContactGraphQlReCaptcha\Test\Unit\Model;

use Magento\ContactGraphQl\Model\Resolver\ContactUs;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Secomm\ContactGraphQlReCaptcha\Model\WebapiConfigProvider;

/**
 * ReCaptcha validation config is provided ONLY for the contactUs mutation
 * (ContactUs resolver, method `resolve`) AND only when the `contact` captcha
 * is enabled. Every other endpoint or disabled captcha must return null.
 */
class WebapiConfigProviderTest extends TestCase
{
    private const CAPTCHA_ID = 'contact';

    private IsCaptchaEnabledInterface|MockObject $isEnabled;
    private ValidationConfigResolverInterface|MockObject $configResolver;
    private WebapiConfigProvider $model;

    protected function setUp(): void
    {
        $this->isEnabled = $this->createMock(IsCaptchaEnabledInterface::class);
        $this->configResolver = $this->createMock(ValidationConfigResolverInterface::class);
        $this->model = new WebapiConfigProvider($this->isEnabled, $this->configResolver);
    }

    /**
     * Happy path: endpoint matches ContactUs::resolve and captcha enabled.
     */
    public function testReturnsConfigForContactUsMutationWhenEnabled(): void
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getServiceMethod')->willReturn('resolve');
        $endpoint->method('getServiceClass')->willReturn(ContactUs::class);

        $config = $this->createMock(ValidationConfigInterface::class);

        $this->isEnabled->expects($this->once())
            ->method('isCaptchaEnabledFor')
            ->with(self::CAPTCHA_ID)
            ->willReturn(true);
        $this->configResolver->expects($this->once())
            ->method('get')
            ->with(self::CAPTCHA_ID)
            ->willReturn($config);

        $this->assertSame($config, $this->model->getConfigFor($endpoint));
    }

    /**
     * Some other resolver (e.g. newsletter subscribe) must NOT get a config.
     */
    public function testReturnsNullForNonContactUsEndpoint(): void
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getServiceMethod')->willReturn('resolve');
        $endpoint->method('getServiceClass')->willReturn('Some_Other_Resolver_Class');

        $this->isEnabled->expects($this->never())->method('isCaptchaEnabledFor');
        $this->configResolver->expects($this->never())->method('get');

        $this->assertNull($this->model->getConfigFor($endpoint));
    }

    /**
     * Endpoint matches but captcha disabled: resolver never queried.
     */
    public function testReturnsNullWhenCaptchaDisabled(): void
    {
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getServiceMethod')->willReturn('resolve');
        $endpoint->method('getServiceClass')->willReturn(ContactUs::class);

        $this->isEnabled->expects($this->once())
            ->method('isCaptchaEnabledFor')
            ->with(self::CAPTCHA_ID)
            ->willReturn(false);
        $this->configResolver->expects($this->never())->method('get');

        $this->assertNull($this->model->getConfigFor($endpoint));
    }

    /**
     * Wrong service method (e.g. REST endpoint, not the GraphQL `resolve`).
     */
    public function testReturnsNullForNonResolveMethod(): void
    {
        // 'execute' is a REST action, not the GraphQL resolver method
        $endpoint = $this->createMock(EndpointInterface::class);
        $endpoint->method('getServiceMethod')->willReturn('execute');
        $endpoint->method('getServiceClass')->willReturn(ContactUs::class);

        // neither dependency is consulted when the method check fails first
        $this->isEnabled->expects($this->never())->method('isCaptchaEnabledFor');
        $this->configResolver->expects($this->never())->method('get');

        $this->assertNull($this->model->getConfigFor($endpoint));
    }
}
