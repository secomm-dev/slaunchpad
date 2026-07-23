<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2012, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
*
* Provider writed by xbreaker | https://github.com/xbreaker/hybridauth
*/

namespace Mageplaza\SocialLoginPro\Model\Providers;

use Exception;
use Hybridauth\Adapter\OAuth2 as Hybrid_Provider_Model_OAuth2;
use HybridAuth\Exception\RuntimeException;
use Hybridauth\User\Profile;

/**
 * Hybrid_Providers_Mailru provider adapter based on OAuth2 protocol
 *
 * docs :: https://o2.mail.ru/docs
 * Class Mailru
 *
 * @package Mageplaza\SocialLoginPro\Model\Providers
 */
class Mailru extends Hybrid_Provider_Model_OAuth2
{

    /**
     * @var string
     */
    protected $apiBaseUrl = 'https://oauth.mail.ru';
    /**
     * @var string
     */
    protected $authorizeUrl = 'https://oauth.mail.ru/login';
    /**
     * @var string
     */
    protected $accessTokenUrl = 'https://oauth.mail.ru/token';
    /**
     * @var string
     */
    protected $accessTokenName = 'session_key';

    /**
     * @var string
     */
    protected $scope = 'userinfo';

    /**
     * @return Profile
     * @throws Exception
     */
    public function getUserProfile()
    {
        $params   = [
            'access_token' => $this->getStoredData('access_token')
        ];
        $response = $this->apiRequest('userinfo', 'GET', $params);
        if (!isset($response->id)) {
            throw new RuntimeException("User profile request failed!
             {$this->providerId} returned an invalid response.", 6);
        }
        $userProfile                = new  Profile();
        $userProfile->identifier    = (property_exists($response, 'id')) ? $response->id : "";
        $userProfile->firstName     = (property_exists($response, 'first_name')) ? $response->first_name : "";
        $userProfile->lastName      = (property_exists($response, 'last_name')) ? $response->last_name : "";
        $userProfile->displayName   = (property_exists($response, 'nickname')) ? $response->nickname : "";
        $userProfile->photoURL      = (property_exists($response, 'image')) ? $response->image : "";
        $userProfile->gender        = (property_exists($response, 'gender'))
            ? ($response->gender === 'm' ? 'mails' : 'female') : "";
        $userProfile->email         = (property_exists($response, 'email')) ? $response->email : "";
        $userProfile->emailVerified = (property_exists($response, 'email')) ? $response->email : "";

        if (property_exists($response, 'birthday')) {
            [$birthday_day, $birthday_month, $birthday_year] = explode('.', $response->birthday);

            $userProfile->birthDay   = (int) $birthday_day;
            $userProfile->birthMonth = (int) $birthday_month;
            $userProfile->birthYear  = (int) $birthday_year;
        }

        return $userProfile;
    }
}
