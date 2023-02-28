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

namespace OCA\KiltNextcloudLogin\Service;

use Hybridauth\Storage\StorageInterface;
use OCP\ISession;

class SessionStorage implements StorageInterface
{
    /** @var ISession */
    private $session;

    public function __construct(ISession $session)
    {
        $this->session = $session;
    }

    /**
    * {@inheritdoc}
    */
    public function get($key)
    {
        return $this->session->get($key);
    }

    /**
    * {@inheritdoc}
    */
    public function set($key, $value)
    {
        $this->session->set($key, $value);
    }

    /**
    * {@inheritdoc}
    */
    public function delete($key)
    {
        $this->session->remove($key);
    }

    /**
    * {@inheritdoc}
    */
    public function deleteMatch($key)
    {
        foreach ($this->session as $k => $v) {
            if (strstr($k, $key)) {
                $this->delete($k);
            }
        }
    }

    /**
    * {@inheritdoc}
    */
    public function clear()
    {
        $this->session->clear();
    }
}
