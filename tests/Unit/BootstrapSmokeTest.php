<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The harness itself: tiger-core's autoloader is resolved (the Tiger and Zend classes), and the
 * module's Docs loader maps the ZF1 layout so the engine classes load. If this fails nothing else
 * is meaningful.
 */
final class BootstrapSmokeTest extends TestCase
{
    public function testTigerCoreIsOnTheAutoloader(): void
    {
        $this->assertTrue(class_exists('Tiger_Application'), 'tiger-core autoloader is wired');
        $this->assertTrue(class_exists('Zend_Config'), 'Zend_* resolves via tigerzf');
    }

    public function testModuleClassesLoad(): void
    {
        $this->assertTrue(class_exists('Docs_Model_Docs'), 'models/Docs.php → Docs_Model_Docs');
        $this->assertTrue(class_exists('Docs_Model_Index'), 'models/Index.php → Docs_Model_Index');
        $this->assertTrue(class_exists('Docs_Model_Remote'), 'models/Remote.php → Docs_Model_Remote');
    }
}
