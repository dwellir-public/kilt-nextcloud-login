## Kilt Nextcloud Login

This app makes it possible to use an web3 identity to log in to Nextcloud. Authentication is done using the sporran wallet.

## Installation
Download the source code, put it on the nextcloud/apps directory on your server and then run the following shell commands (line by line):
```
cd nextcloud/apps
git clone git@github.com:dwellir-public/kilt-nextcloud-login.git
mv kilt-nextcloud-login kiltnextcloudlogin
cd kiltnextcloudlogin
composer install
cd 3rdparty
composer install
cd ../vendor/dwellir-public/kilt-sdk
composer install
cd ../../..
mkdir js
cd js
wget https://code.jquery.com/jquery-3.6.3.min.js
wget https://unpkg.com/@kiltprotocol/sdk-js@dev/dist/sdk-js.min.umd.js
```

Then log in to your nextcloud instance and enable the plugin.

## Attribution

This project is based on the great [Social Login](https://github.com/zorn-v/nextcloud-social-login) app. We wish to extend thanks to zorn-z and all the other great developers that have contributed to the project.
