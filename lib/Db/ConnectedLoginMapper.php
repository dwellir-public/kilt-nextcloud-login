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

namespace OCA\KiltNextcloudLogin\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db;
use OCP\AppFramework\Db\QBMapper;

class ConnectedLoginMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'sociallogin_connect', ConnectedLogin::class);
    }

    /**
     * @param string $identifier social login identifier
     * @return ConnectedLogin|null
     */
    public function find(string $identifier) {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where(
                    $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier))
            );

        try {
            return $this->findEntity($qb);
        } catch(Db\DoesNotExistException $e) {
            return null;
        } catch(Db\MultipleObjectsReturnedException $e) {
            return null;
        }
    }

    /**
     * @param string $identifier social login identifier
     * @return string|null Nextcloud user id that corresponds to the social login identifier
     */
    public function findUID($identifier)
    {
        $login = $this->find($identifier);
        return $login == null ? null : $login->uid;
    }

    /**
     * @param string $uid Nextcloud user id
     * @param string $identifier social login identifier
     */
    public function connectLogin($uid, $identifier)
    {
        $l = new ConnectedLogin();
        $l->setUid($uid);
        $l->setIdentifier($identifier);
        $this->insert($l);
    }

    /**
     * @param string $identifier social login identifier
     */
    public function disconnectLogin($identifier)
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->tableName)
            ->where(
                $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier))
            )
        ;
        if (method_exists($qb, 'executeStatement')) {
            $qb->executeStatement();
        } else {
            $qb->execute();
        }
    }

    /**
     * @param string $uid Nextcloud user id
     */
    public function disconnectAll($uid)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->delete($this->tableName)
            ->where(
                $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
            )
        ;
        if (method_exists($qb, 'executeStatement')) {
            $qb->executeStatement();
        } else {
            $qb->execute();
        }
    }

    /**
     * @param string $uid
     * @return array containing the social login identifiers of all connected logins
     */
    public function getConnectedLogins($uid)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

        $entities = $this->findEntities($qb);
        $result = [];
        foreach ($entities as $e) {
            $result[] = $e->identifier;
        }

        return $result;
    }

}
