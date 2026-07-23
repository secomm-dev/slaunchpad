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
 * @package   Mageplaza_SocialLoginPro
 * @copyright Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license   https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\SocialLoginPro\Plugin\Helper;

use Closure;
use Mageplaza\SocialLoginPro\Helper\Data as HelperData;
use Mageplaza\SocialLoginPro\Model\Providers\Disqus;
use Mageplaza\SocialLoginPro\Model\Providers\Mailru;
use Mageplaza\SocialLoginPro\Model\Providers\Odnoklassniki;
use Mageplaza\SocialLoginPro\Model\Providers\Pinterest;
use Mageplaza\SocialLoginPro\Model\Providers\Steam;

/**
 * Class Social
 *
 * @package Mageplaza\SocialLoginPro\Plugin\Helper
 */
class Social
{
    /**
     * @var HelperData
     */
    protected $helperData;

    /**
     * Social constructor.
     *
     * @param HelperData $helperData
     */
    public function __construct(HelperData $helperData)
    {
        $this->helperData = $helperData;
    }

    /**
     * @param \Mageplaza\SocialLogin\Helper\Social $subject
     * @param array $result
     *
     * @return array
     */
    public function afterGetSocialTypesArray(\Mageplaza\SocialLogin\Helper\Social $subject, array $result)
    {
        return array_merge(
            $result,
            [
                'disqus'        => 'Disqus',
                'mailru'        => 'Mailru',
                'odnoklassniki' => 'Odnoklassniki',
                'steam'         => 'Steam',
                'pinterest'     => 'Pinterest'
            ]
        );
    }

    /**
     * @param \Mageplaza\SocialLogin\Helper\Social $subject
     * @param Closure $proceed
     * @param $type
     *
     * @return mixed
     */
    public function aroundGetSocialConfig(\Mageplaza\SocialLogin\Helper\Social $subject, Closure $proceed, $type)
    {
        $result = $proceed($type);
        if (empty($result)) {
            $apiData = [
                'Disqus'        => ['wrapper' => ['class' => Disqus::class]],
                'Mailru'        => ['wrapper' => ['class' => Mailru::class]],
                'Odnoklassniki' => [
                    'wrapper'    => ['class' => Odnoklassniki::class],
                    'public_key' => $this->helperData->getPublicKeyOdnoklassniki()
                ],
                'Steam'         => ['wrapper' => ['class' => Steam::class]],
                'Pinterest'     => ['wrapper' => ['class' => Pinterest::class]]
            ];
            if ($type && array_key_exists($type, $apiData)) {
                return $apiData[$type];
            }
        }

        return $result;
    }

    /**
     * @param \Mageplaza\SocialLogin\Helper\Social $subject
     * @param Closure $proceed
     * @param $type
     *
     * @return mixed
     */
    public function aroundGetAuthUrl(\Mageplaza\SocialLogin\Helper\Social $subject, Closure $proceed, $type)
    {
        $authUrl = $this->helperData->getBaseAuthUrl();

        $type = $this->helperData->setType($type);
        switch ($type) {
            case 'Facebook':
                $param = 'hauth_done=' . $type;
                break;
            case 'Live':
                $param = 'live.php';
                break;
            case 'Yahoo':
            case 'Instagram':
                $param = 'instagram.php';
                break;
            case 'Twitter':
            case 'Vkontakte':
            case 'Pinterest':
            case 'Zalo':
                return $authUrl;
            default:
                $param = 'hauth.done=' . $type;
        }
        if ($type === 'Live' || $type === 'Instagram') {
            return $authUrl . $param;
        }

        return $authUrl . ($param ? (strpos($authUrl, '?') ? '&' : '?') . $param : '');
    }
}
