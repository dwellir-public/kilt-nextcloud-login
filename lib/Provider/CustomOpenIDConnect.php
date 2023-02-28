<?php
/*
*    Kilt Nextcloud Login; This app makes it possible to use an web3 identity to
*    log in to Nextcloud.
*    This project is based on the great Social Login
*    < https://github.com/zorn-v/nextcloud-social-login) app. We wish to extend
*    thanks to zorn-z and all the other great developers that have contributed to
*    the project.
*
*    Copyright (C) 2018-2023 The team behind the Nextcloud Social Login
*    < https://github.com/zorn-v/nextcloud-social-login >
*
*    Copyright (C) 2023 Dwellir AB < https://dwellir.com/ >
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU Affero General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU Affero General Public License for more details.
*
*    You should have received a copy of the GNU Affero General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace OCA\KiltNextcloudLogin\Provider;

use Hybridauth\User;
use Hybridauth\Data;
use Hybridauth\Exception\Exception;

class CustomOpenIDConnect extends CustomOAuth2
{
    protected function validateAccessTokenExchange($response)
    {
        $collection = parent::validateAccessTokenExchange($response);
        if ($collection->exists('id_token')) {
            $idToken = $collection->get('id_token');
            //get payload from id_token
            $parts = explode('.', $idToken);
            list($headb64, $payload) = $parts;
            // JWT token is base64url encoded
            $data = base64_decode(str_pad(strtr($payload, '-_', '+/'), strlen($payload) % 4, '=', STR_PAD_RIGHT));
            $this->storeData('user_data', $data);
        } else {
            throw new Exception('No id_token was found.');
        }
        return $collection;
    }

    public function getUserProfile()
    {
        $userData = $this->getStoredData('user_data');
        $user = json_decode($userData);
        $data = new Data\Collection($user);

        $displayNameClaim = $this->config->get('displayname_claim');

        $userProfile = new User\Profile();
        $userProfile->identifier  = $data->get('sub');
        $userProfile->displayName = $data->get($displayNameClaim) ?: $data->get('name') ?: $data->get('preferred_username');
        $userProfile->photoURL    = $data->get('picture');
        $userProfile->email       = $data->get('email');
        if (!is_string($userProfile->photoURL)) {
            $userProfile->photoURL = null;
        }
        if ($data->exists('street_address')) {
            $userProfile->address = $data->get('street_address');
        }
        if (null !== $groups = $this->getGroups($data)) {
            $userProfile->data['groups'] = $groups;
        }
        if ($groupMapping = $this->config->get('group_mapping')) {
            $userProfile->data['group_mapping'] = $groupMapping;
        }

        $userInfoUrl = trim($this->config->get('endpoints')['user_info_url']);
        if (!empty($userInfoUrl)) {
            $profile = new Data\Collection( $this->apiRequest($userInfoUrl) );
            if (empty($userProfile->identifier)) {
                $userProfile->identifier = $profile->get('sub');
            }
            $userProfile->displayName = $profile->get($displayNameClaim) ?: $profile->get('name') ?: $profile->get('preferred_username') ?: $profile->get('nickname');
            if (!$userProfile->photoURL) {
                $userProfile->photoURL = $profile->get('picture') ?: $profile->get('avatar');
            }
            if (!is_string($userProfile->photoURL)) {
                $userProfile->photoURL = null;
            }
            if (preg_match('#<img.+src=["\'](.+?)["\']#', $userProfile->photoURL, $m)) {
                $userProfile->photoURL = $m[1];
            }
            if (!$userProfile->email) {
                $userProfile->email = $profile->get('email');
            }
            if (empty($userProfile->data['groups']) && null !== $groups = $this->getGroups($profile)) {
                $userProfile->data['groups'] = $groups;
            }
        }

        return $userProfile;
    }
}
