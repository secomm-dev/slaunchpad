<?php
/**
 * Copyright © Secomm. All rights reserved.
 */
declare(strict_types=1);

namespace Secomm\ContactGraphQlReCaptcha\Model;

use Magento\ContactGraphQl\Model\Resolver\ContactUs;
use Magento\Framework\Exception\InputException;
use Magento\ReCaptchaUi\Model\IsCaptchaEnabledInterface;
use Magento\ReCaptchaUi\Model\ValidationConfigResolverInterface;
use Magento\ReCaptchaValidationApi\Api\Data\ValidationConfigInterface;
use Magento\ReCaptchaWebapiApi\Api\Data\EndpointInterface;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;

class WebapiConfigProvider implements WebapiValidationConfigProviderInterface
{
    private const CAPTCHA_ID = 'contact';

    /**
     * @param IsCaptchaEnabledInterface $isEnabled
     * @param ValidationConfigResolverInterface $configResolver
     */
    public function __construct(
        private IsCaptchaEnabledInterface $isEnabled,
        private ValidationConfigResolverInterface $configResolver
    ) {
    }

    /**
     * @param EndpointInterface $endpoint
     * @return ValidationConfigInterface|null
     * @throws InputException
     */
    public function getConfigFor(EndpointInterface $endpoint): ?ValidationConfigInterface
    {
        if ($endpoint->getServiceMethod() === 'resolve'
            && $endpoint->getServiceClass() === ContactUs::class
            && $this->isEnabled->isCaptchaEnabledFor(self::CAPTCHA_ID)
        ) {
            return $this->configResolver->get(self::CAPTCHA_ID);
        }

        return null;
    }
}
