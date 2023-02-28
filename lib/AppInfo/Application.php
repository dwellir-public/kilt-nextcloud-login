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

namespace OCA\KiltNextcloudLogin\AppInfo;

use OCA\KiltNextcloudLogin\AlternativeLogin\DefaultLoginShow;
use OCA\KiltNextcloudLogin\Db\ConnectedLoginMapper;
use OCA\KiltNextcloudLogin\Service\ProviderService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserLoggedOutEvent;
use OCP\Util;

class Application extends App implements IBootstrap
{
    private $appName = 'kiltnextcloudlogin';
    private $regContext;

    public function __construct()
    {
        parent::__construct($this->appName);
    }

    public function register(IRegistrationContext $context): void
    {
        require __DIR__ . '/../../3rdparty/autoload.php';

        $this->regContext = $context;
    }

    public function boot(IBootContext $context): void
    {
        Util::addStyle($this->appName, 'styles');

        $l = $this->query(IL10N::class);
        $config = $this->query(IConfig::class);

        $dispatcher = $this->query(IEventDispatcher::class);
        $dispatcher->addListener(BeforeUserDeletedEvent::class, [$this, 'preDeleteUser']);

        $userSession = $this->query(IUserSession::class);
        if ($userSession->isLoggedIn()) {
            $uid = $userSession->getUser()->getUID();
            $session = $this->query(ISession::class);
            if ($config->getUserValue($uid, $this->appName, 'disable_password_confirmation')) {
                $session->set('last-password-confirm', time());
            }
            if ($logoutUrl = $session->get('sociallogin_logout_url')) {
                $dispatcher->addListener(UserLoggedOutEvent::class, function () use ($logoutUrl) {
                    header('Location: ' . $logoutUrl);
                    exit();
                });
            }
            return;
        }

        $providerService = $this->query(ProviderService::class);
        $request = $this->query(IRequest::class);

        $providersCount = 0;
        ++$providersCount;
        $loginClass = $providerService->getLoginClass('KILT', [ 'name' => 'KILT', 'title' => 'KILT', 'baseUrl' => 'none', 'logoutUrl' => null, 'ssoSecret' => 'none', 'style' => 'openid', 'defaultGroup' => null], 'kilt');
        $this->regContext->registerAlternativeLogin($loginClass);


        if (PHP_SAPI !== 'cli') {
            $useLoginRedirect = $providersCount === 1
                && $request->getMethod() === 'GET'
                && !$request->getParam('noredir')
                && $config->getSystemValue('social_login_auto_redirect', false);
            if ($useLoginRedirect && $request->getPathInfo() === '/login') {
                $login = $this->query($loginClass);
                $login->load();
                header('Location: ' . $login->getLink());
                exit();
            }

            $hideDefaultLogin = $providersCount > 0 && $config->getAppValue($this->appName, 'hide_default_login');
            if ($hideDefaultLogin && $request->getPathInfo() === '/login') {
                $this->regContext->registerAlternativeLogin(DefaultLoginShow::class);
            }
        }
    }

    public function preDeleteUser(BeforeUserDeletedEvent $event)
    {
        $user = $event->getUser();
        $this->query(ConnectedLoginMapper::class)->disconnectAll($user->getUID());
    }

    private function query($className)
    {
        return $this->getContainer()->get($className);
    }
}
