<?php

namespace Mageplaza\SocialLoginPro\Model\Providers;

use Hybridauth\Provider\Pinterest as PinterestLib;

/*!
* HybridAuth
* http://hybridauth.sourceforge.net | http://github.com/hybridauth/hybridauth
* (c) 2009-2013, HybridAuth authors | http://hybridauth.sourceforge.net/licenses.html
*/

/**
 * Hybrid_Providers_Pinterest (By Eduardo Marcolino - https://github.com/eduardo-marcolino)
 */
class Pinterest extends PinterestLib
{
    /**
     * {@inheritdoc}
     */
    protected $scope = 'user_accounts:read';

    /**
     * {@inheritdoc}
     */
    protected $apiBaseUrl = 'https://api.pinterest.com/v5';

    /**
     * {@inheritdoc}
     */
    protected $authorizeUrl = 'https://www.pinterest.com/oauth';

    /**
     * {@inheritdoc}
     */
    protected $accessTokenUrl = 'https://api.pinterest.com/v5/oauth/token';

    /**
     * {@inheritdoc}
     */
    protected function initialize()
    {
        $this->AuthorizeUrlParameters = [
            'response_type' => 'code',
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->callback,
            'scope'         => $this->scope,
        ];

        $this->tokenExchangeParameters = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $this->callback
        ];

        $refreshToken = $this->getStoredData('refresh_token');
        if (!empty($refreshToken)) {
            $this->tokenRefreshParameters = [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
            ];
        }

        $this->apiRequestHeaders    = [
            'Authorization' => 'Bearer ' . $this->getStoredData('access_token')
        ];
        $base64                     = base64_encode($this->clientId . ':' . $this->clientSecret);
        $this->tokenExchangeHeaders = [
            'Authorization' => 'Basic {' . $base64 . '}',
            'Content-Type'  => 'application/x-www-form-urlencoded'
        ];
    }

    /**
     * @inheritdoc
     */
    public function getUserProfile()
    {
        $response = $this->apiRequest('user_account');

        $userProfile = new  \Hybridauth\User\Profile();

        $userProfile->identifier  = $response->username;
        $userProfile->photoURL    = $response->profile_image;
        $userProfile->webSiteURL  = $response->website_url;
        $userProfile->displayName = $response->username;

        return $userProfile;
    }
}
