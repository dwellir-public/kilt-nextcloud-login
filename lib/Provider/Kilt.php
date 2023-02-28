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

require_once __DIR__ . '/../../vendor/dwellir-public/kilt-sdk/vendor/autoload.php';

use Hybridauth\Adapter\AbstractAdapter;
use Hybridauth\Adapter\AdapterInterface;
use Hybridauth\Exception\AuthorizationDeniedException;
use Hybridauth\Exception\InvalidAuthorizationStateException;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\HttpClient;
use Hybridauth\User;
use KiltSdkPhp\KiltSdk;
use KiltSdkPhp\Modules\Did\DidSignatureVerificationInput;

/**
 * This class can be used to simplify the authentication flow of Discourse based service providers.
 *
 * Subclasses (i.e., providers adapters) can either use the already provided methods or override
 * them when necessary.
 */
abstract class HybridauthKilt extends AbstractAdapter implements AdapterInterface
{
    /**
    * Discourse base url
    *
    * @var string
    */
    protected $baseUrl = '';

    /**
    * Discourse SSO secret
    *
    * @var string
    */
    protected $ssoSecret = '';

    /**
    * {@inheritdoc}
    */
    protected function configure()
    {
        $this->baseUrl    = 'http://foo.bar';
        $this->ssoSecret = 'foo';
        $this->setCallback($this->baseUrl);
    }

    /**
    * {@inheritdoc}
    */
    protected function initialize()
    {
    }

    /**
    * {@inheritdoc}
    */
    public function authenticate()
    {
        $this->logger->info(sprintf('%s::authenticate()', get_class($this)));

        if ($this->isConnected()) {
            return true;
        }

        if (empty($_GET['sso'])) {
            $this->authenticateBegin();
        } else {
            return $this->authenticateFinish();
        }

        return null;
    }

    /**
    * {@inheritdoc}
    */
    public function isConnected()
    {
        return (bool) $this->storage->get($this->providerId . '.user');
    }

    /**
    * {@inheritdoc}
    */
    public function disconnect()
    {
        $this->storage->delete($this->providerId . '.user');

        return true;
    }

    protected function authenticateBegin()
    {
        $payload = $this->encodeAuthenticatePayload($this->getAuthenticatePayload());
	$sig = $this->signAuthenticatePayload($payload);
	$sso = $payload;
	$this->storage->set('sso_kilt', $sso);

// todo: break this out into a template
?><!DOCTYPE html>
<html lang="en" style='background-color: rgb(0, 130, 201);'>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script src="/apps/kiltnextcloudlogin/js/sdk-js.min.umd.js"></script>
  <script src="/apps/kiltnextcloudlogin/js/jquery-3.6.3.min.js"></script>
  <link rel="stylesheet" href="/core/css/server.css">
  <link rel="stylesheet" href="/core/css/guest.css">
  <title>Login with KILT</title>

</head>

<body>
  <div id="message"></div>
  <script>
	setTimeout(function() {
	    const username = prompt("Enter your username");
	    kilt.sporran.signWithDid('<?php echo $sig; ?>').then( function(res) {
		uri = '/index.php/apps/kiltnextcloudlogin/kilt/KILT?sso=<?php echo $sso;?>&sig=<?php echo $sig;?>&signature=' + res.signature + '&didKeyUri=' + encodeURIComponent(res.didKeyUri) + '&username=' + username;
		window.location = uri;
	    });
	}, 2000);
</script>

</body>
</html>

<?php
exit;
    }

    protected function authenticateFinish()
    {
        $this->logger->debug(
            sprintf('%s::authenticateFinish(), callback url:', get_class($this)),
            [HttpClient\Util::getCurrentUrl(true)]
        );

        $sso = filter_input(INPUT_GET, 'sso');
        $sig = filter_input(INPUT_GET, 'sig');
	$username = filter_input(INPUT_GET, 'username');
	$didKeyUri = urldecode(filter_input(INPUT_GET, 'didKeyUri'));
        $signature = filter_input(INPUT_GET, 'signature');

	if($this->storage->get('sso_kilt') != $sso) {
		$this->storage->delete('sso_kilt');
		return false;
	}
	$this->storage->delete('sso_kilt');

	$kilt = new KiltSdk();
	$res = $kilt->connect('wss://spiritnet.kilt.io');

	$w3nres = $kilt->did->queryByWeb3Name($username);
	if (!isset($w3nres->response)) {
	    $kilt->disconnect();
	    $kilt->exit();
	    return false;
	}

	try {
	    $res = $kilt->did->verifyDidSignature(new DidSignatureVerificationInput([
		'keyUri' => $didKeyUri,
		'message' => $sig,
		'signature' => $signature,
		'expectedSigner' => 'did:kilt:' . $w3nres->response->identifier
	    ]));
	} catch (\Exception $e) {
	    $kilt->disconnect();
	    $kilt->exit();
	    return false;
	}
	$kilt->disconnect();
	$kilt->exit();

        $userProfile = new User\Profile();
        $userProfile->identifier        = $username;
        $userProfile->displayName       = $username;
        $this->storage->set($this->providerId . '.user', $userProfile);
    }

    public function getUserProfile()
    {
        $userProfile = $this->storage->get($this->providerId . '.user');

        if (! is_object($userProfile)) {
            throw new UnexpectedApiResponseException('Provider returned an unexpected response.');
        }

        return $userProfile;
    }


    private function getAuthenticatePayload()
    {
        $nonce = substr(base64_encode(random_bytes(64)), 0, 30);
        $this->storeData('authorization_nonce', $nonce);

        return [
            'nonce' => $nonce,
            'return_sso_url' => $this->callback
        ];
    }

    private function verifyAuthenticatePayload($payload)
    {
        $nonce = $payload['nonce'];

        if ($this->getStoredData('authorization_nonce') != $nonce) {
            throw new InvalidAuthorizationStateException(
                'The authorization nonce [nonce=' . substr(htmlentities($nonce), 0, 100). '] '
                    . 'of this page is either invalid or has already been consumed.'
            );
        }

        $this->deleteStoredData('authorization_nonce');
    }

    private function encodeAuthenticatePayload($payload)
    {
        return base64_encode(http_build_query($payload));
    }

    private function decodeAuthenticatePayload($payload)
    {
        $ret = [];
        parse_str(base64_decode($payload), $ret);
        return $ret;
    }

    private function signAuthenticatePayload($payload)
    {
        return hash_hmac('sha256', $payload, $this->ssoSecret);
    }
}

class Kilt extends HybridauthKilt
{
    public function getUserProfile()
    {
        $userProfile = parent::getUserProfile();

        if (null !== $groups = $userProfile->data['groups']) {
            $userProfile->data['groups'] = $this->strToArray($groups);
        }

        return $userProfile;
    }

    private function strToArray($str)
    {
        return array_filter(
            array_map('trim', explode(',', $str)),
            function ($val) { return $val !== ''; }
        );
    }
}
