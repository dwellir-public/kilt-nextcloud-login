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

namespace OCA\KiltNextcloudLogin\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Util;
use OCA\KiltNextcloudLogin\Db\ConnectedLoginMapper;

class SettingsController extends Controller
{
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IUserSession */
    private $userSession;
    /** @var IL10N */
    private $l;
    /** @var ConnectedLoginMapper */
    private $socialConnect;

    public function __construct(
        $appName,
        IRequest $request,
        IConfig $config,
        IURLGenerator $urlGenerator,
        IUserSession $userSession,
        IL10N $l,
        ConnectedLoginMapper $socialConnect
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->l = $l;
        $this->socialConnect = $socialConnect;
    }

    public function saveAdmin($options, $providers, $custom_providers) {
        foreach ($options as $k => $v) {
            $this->config->setAppValue($this->appName, $k, $v ? true : false);
        }

        $this->config->setAppValue($this->appName, 'oauth_providers', json_encode($providers));

        if (is_array($custom_providers)) {
            try {
                $names = array_keys($providers);
                foreach ($custom_providers as $provType => $provs) {
                    $this->checkProviders($provs, $names);
                    $custom_providers[$provType] = array_values($provs);
                }
            } catch (\Exception $e) {
                return new JSONResponse(['message' => $e->getMessage()]);
            }
        }
        $this->config->setAppValue($this->appName, 'custom_providers', json_encode($custom_providers));

        return new JSONResponse(['success' => true]);
    }

    private function checkProviders($providers, &$names)
    {
        if (!is_array($providers)) {
            return;
        }
        foreach ($providers as $provider) {
            $name = $provider['name'];
            if (empty($name)) {
                throw new \Exception($this->l->t('Provider name cannot be empty'));
            }
            if (in_array($name, $names)) {
                throw new \Exception($this->l->t('Duplicate provider name "%s"', $name));
            }
            if (preg_match('#[^0-9a-z_.@-]#i', $name)) {
                throw new \Exception($this->l->t('Invalid provider name "%s". Allowed characters "0-9a-z_.@-"', $name));
            }
            $names[] = $name;
        }
    }

    /**
     * @NoAdminRequired
     * @PasswordConfirmationRequired
     */
    public function savePersonal($disable_password_confirmation)
    {
        $uid = $this->userSession->getUser()->getUID();
        $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', $disable_password_confirmation ? 1 : 0);
        return new JSONResponse(['success' => true]);
    }

    /**
     * @NoAdminRequired
     */
    public function disconnectSocialLogin($login)
    {
        $this->socialConnect->disconnectLogin($login);
        return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section'=>'sociallogin']));
    }
}
