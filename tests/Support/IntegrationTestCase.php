<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Support;

use PHPUnit\Framework\TestCase;
use Tiger_Model_Table;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Db;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Base for TigerDocs tests that need core's real `page` table (the DB doc-override tier lives there).
 *
 * TigerDocs ships no tables of its own, so the schema is core's: the `page` table is built once per
 * process from tiger-core's own migration (`migrations/0014_create_page.php`, resolved from the same
 * tiger-core checkout the bootstrap loaded) after dropping any previous copy. Each test runs inside a
 * transaction rolled back in tearDown.
 *
 * Self-skips when TIGER_TEST_DB_NAME is unset:
 *   TIGER_TEST_DB_NAME (required)  TIGER_TEST_DB_SOCKET (unix socket; overrides host/port)
 *   TIGER_TEST_DB_HOST/_PORT (default 127.0.0.1/3306)  TIGER_TEST_DB_USER/_PASS (default root/'')
 *
 * NEVER point TIGER_TEST_DB_* at a dev or prod database — setUp DROPS the `page` table.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static ?Zend_Db_Adapter_Abstract $sharedDb = null;
    private static bool $schemaReady = false;

    protected Zend_Db_Adapter_Abstract $db;

    protected function setUp(): void
    {
        parent::setUp();

        $name = getenv('TIGER_TEST_DB_NAME');
        if ($name === false || $name === '') {
            $this->markTestSkipped('Integration DB not configured (set TIGER_TEST_DB_NAME to run).');
        }

        $this->db = self::adapter((string) $name);
        $this->ensureSchema();

        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_NonPersistent());
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg('');
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            try { $this->db->rollBack(); } catch (\Throwable $e) { /* test closed the txn — fine */ }
        }
        Zend_Auth::getInstance()->clearIdentity();
        Tiger_Model_Table::setActor(null);
        Tiger_Model_Table::setOrg('');
        parent::tearDown();
    }

    /** Write a signed-in identity (non-persistent storage) carrying an org. */
    protected function login(string $orgId, string $userId = 'user-1', string $role = 'admin'): void
    {
        Zend_Auth::getInstance()->getStorage()->write((object) [
            'org_id' => $orgId, 'user_id' => $userId, 'role' => $role,
        ]);
    }

    private static function adapter(string $name): Zend_Db_Adapter_Abstract
    {
        if (self::$sharedDb === null) {
            $cfg = [
                'dbname'   => $name,
                'username' => getenv('TIGER_TEST_DB_USER') ?: 'root',
                'password' => getenv('TIGER_TEST_DB_PASS') ?: '',
                'charset'  => 'utf8mb4',
            ];
            $socket = getenv('TIGER_TEST_DB_SOCKET');
            if ($socket) {
                $cfg['unix_socket'] = $socket;
            } else {
                $cfg['host'] = getenv('TIGER_TEST_DB_HOST') ?: '127.0.0.1';
                $cfg['port'] = (int) (getenv('TIGER_TEST_DB_PORT') ?: 3306);
            }
            self::$sharedDb = Zend_Db::factory('Pdo_Mysql', $cfg);
            Zend_Db_Table_Abstract::setDefaultAdapter(self::$sharedDb);
            Zend_Registry::set('Zend_Db', self::$sharedDb);
        }
        return self::$sharedDb;
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }
        $migration = require TIGER_CORE_PATH . '/migrations/0014_create_page.php';
        foreach ($migration['down'] as $sql) { $this->db->query($sql); }
        foreach ($migration['up'] as $sql)   { $this->db->query($sql); }
        self::$schemaReady = true;
    }
}
