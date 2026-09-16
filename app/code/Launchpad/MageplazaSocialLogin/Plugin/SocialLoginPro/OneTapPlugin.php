<?php
declare(strict_types=1);

namespace Launchpad\MageplazaSocialLogin\Plugin\SocialLoginPro;

use Mageplaza\SocialLoginPro\Block\OneTap;

/**
 * Trim the Google app_id read from config before it is rendered as the One Tap
 * data-client_id. The vendor block reads the raw config value
 * (Mageplaza_SocialLoginPro/Block/OneTap.php getClientId()), so an admin paste
 * with leading/trailing whitespace reaches the Google GIS client untrimmed and
 * One Tap fails to initialize. Mirrors Mageplaza_SocialLogin
 * Helper\Social::getAppId(), which already trims the same config path.
 */
class OneTapPlugin
{
    /**
     * Trim leading/trailing whitespace from the configured Google client id.
     * Non-string values (config absent) pass through unchanged.
     *
     * @param OneTap $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetClientId(OneTap $subject, $result)
    {
        return is_string($result) ? trim($result) : $result;
    }
}
