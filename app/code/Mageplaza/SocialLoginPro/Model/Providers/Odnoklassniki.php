<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2015, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
*/

namespace Mageplaza\SocialLoginPro\Model\Providers;

use Exception;
use Hybridauth\Adapter\OAuth2 as Hybrid_Provider_Model_OAuth2;
use Magento\Framework\App\ObjectManager;
use StdClass;

/**
 * Hybrid_Providers_Odnoklassniki provider adapter based on OAuth2 protocol
 * Class Odnoklassniki
 *
 * @package Mageplaza\SocialLoginPro\Model\Providers
 */
class Odnoklassniki extends Hybrid_Provider_Model_OAuth2
{
    /**
     * @var string
     */
    protected $http_info;

    /**
     * @var string
     */
    protected $http_code;
    /**
     * @var string
     */
    protected $apiBaseUrl = 'https://api.ok.ru/fb.do';
    /**
     * @var string
     */
    protected $authorizeUrl = 'http://connect.ok.ru/oauth/authorize';
    /**
     * @var string
     */
    protected $accessTokenUrl = 'http://api.odnoklassniki.ru/oauth/token.do';

    /**
     * @var string
     */
    protected $scope = 'GET_EMAIL';

    /**
     * @param $url
     * @param bool $params
     * @param string $type
     *
     * @return mixed
     */
    private function request($url, $params = [], $type = "GET")
    {
        if ($type === "GET") {
            $url = $url . (strpos($url, '?') ? '&' : '?') . http_build_query($params, '', '&');
        }
        $this->http_info = [];
        $ch              = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            "OAuth/2 Simple PHP Client v0.1.1; HybridAuth http://hybridauth.sourceforge.net/"
        );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, []);
        $response        = curl_exec($ch);
        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->http_info = array_merge($this->http_info, curl_getinfo($ch));
        curl_close($ch);

        return $response;
    }

    /**
     * @param $result
     *
     * @return StdClass|mixed
     */
    private function parseRequestResult($result)
    {
        if (json_decode($result)) {
            return json_decode($result);
        }
        parse_str($result, $output);
        $result = new StdClass();
        foreach ($output as $k => $v) {
            $result->$k = $v;
        }

        return $result;
    }

    /**
     * @return \HybridAuth\User\Profile
     * @throws Exception
     */
    public function getUserProfile()
    {
        // Set fields you want to get from OK user profile.
        // @see https://apiok.ru/wiki/display/api/users.getCurrentUser+ru
        // @see https://apiok.ru/wiki/display/api/fields+ru
        $fields = "UID,LOCALE,FIRST_NAME,LAST_NAME,NAME,GENDER,AGE,BIRTHDAY,HAS_EMAIL,EMAIL,CURRENT_STATUS,CURRENT_STATUS_ID,CURRENT_STATUS_DATE,ONLINE,PHOTO_ID,PIC190X190,PIC640X480,LOCATION";

        $publicKey = $this->config->filter('keys')->get('public_key');
        // Signature
        $sig   = hash(
            'md5',
            'application_key=' . $publicKey . 'fields=' . $fields . 'method=users.getCurrentUser' . hash(
                'md5',
                $this->getStoredData('access_token') . $this->clientSecret
            )
        );
        $param = [
            'application_key' => $publicKey,
            'fields'          => $fields,
            'method'          => 'users.getCurrentUser',
            'sig'             => $sig,
            'access_token'    => $this->getStoredData('access_token')
        ];
        // Signed request
        //        $response = $this->request(
        //            '?application_key=' . $publicKey . '&fields=' . $fields . '&method=users.getCurrentUser&sig=' . $sig . '&access_token=' . $this->getStoredData('access_token')
        //        );
        $response = $this->request($this->apiBaseUrl, $param);
        $response = json_decode($response);
        if (!isset($response->uid)) {
            throw new Exception("User profile request failed! {$this->providerId} returned an invalid response.", 6);
        }

        $userProfile = new  \Hybridauth\User\Profile();

        $userProfile->identifier  = (property_exists($response, 'uid')) ? $response->uid : "";
        $userProfile->firstName   = (property_exists($response, 'first_name')) ? $response->first_name : "";
        $userProfile->lastName    = (property_exists($response, 'last_name')) ? $response->last_name : "";
        $userProfile->displayName = (property_exists($response, 'name')) ? $response->name : "";
        // Get better size of user avatar
        $userProfile->photoURL      = (property_exists($response, 'pic190x190')) ? $response->pic190x190 : "";
        $userProfile->profileURL    = (property_exists($response, 'link')) ? $response->link : "";
        $userProfile->gender        = (property_exists($response, 'gender')) ? $response->gender : "";
        $userProfile->email         = (property_exists($response, 'email')) ? $response->email : "";
        $userProfile->emailVerified = (property_exists($response, 'email')) ? $response->email : "";
        if (property_exists($response, 'birthday')) {
            [$birthday_year, $birthday_month, $birthday_day] = explode('-', $response->birthday);
            $userProfile->birthDay   = (int) $birthday_day;
            $userProfile->birthMonth = (int) $birthday_month;
            $userProfile->birthYear  = (int) $birthday_year;
        }

        return $userProfile;
    }

    /**
     * @param $class
     *
     * @return mixed
     */
    public function getDataObject($class)
    {
        $objectManager = ObjectManager::getInstance();

        return $objectManager->create($class);
    }
}
