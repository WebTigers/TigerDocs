<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace TigerDocs\Tests\Integration;

use Docs_AdminController;
use Docs_IndexController;
use Docs_Model_Docs;
use ReflectionProperty;
use Tiger_Model_Org;
use Tiger_Model_Page;
use TigerDocs\Tests\Support\IntegrationTestCase;
use TigerDocs\Tests\Support\Reflect;

/**
 * TIGER-511 — the DB doc-override tier is served in the right org lane.
 *
 * The controllers used to pass org '' into Docs_Model_Docs::resolve(), leaving the choice of tenant to
 * whatever the core page cascade guessed. They now pass it explicitly: the public site serves the SITE
 * org's overrides (Tiger_Model_Org::siteOrgId(), never the visitor's own org) and the admin help surface
 * the caller's current org. Against core's real `page` table this pins:
 *   - the site org's override wins over the shared '' doc;
 *   - a site org with no override falls back to the shared '' (legacy/global) doc;
 *   - another org's override is never served — neither to the site nor to a signed-in visitor of another org.
 */
final class DocOrgOverrideTest extends IntegrationTestCase
{
    use Reflect;

    private const ORG_SITE  = '01999999-0000-7000-8000-0000000000a1';
    private const ORG_OTHER = '01999999-0000-7000-8000-0000000000b2';
    private const ORG_EMPTY = '01999999-0000-7000-8000-0000000000c3';

    private ReflectionProperty $siteProp;
    private ReflectionProperty $sourcesProp;
    private $siteWas;
    private $sourcesWas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteProp    = new ReflectionProperty(Tiger_Model_Org::class, '_siteOrgId');
        $this->siteWas     = $this->siteProp->getValue();
        $this->sourcesProp = new ReflectionProperty(Docs_Model_Docs::class, '_sources');
        $this->sourcesWas  = $this->sourcesProp->getValue();
        // One public collection with no files on disk — only the DB tier can answer.
        $this->sourcesProp->setValue(null, ['en' => ['guide' => [
            'slug' => 'guide', 'dirs' => [sys_get_temp_dir() . '/tigerdocs-none-' . bin2hex(random_bytes(4))],
            'defaultVis' => 'public', 'source' => 'platform',
        ]]]);

        $this->doc('',             'Shared doc');
        $this->doc(self::ORG_SITE, 'Site org doc');
        $this->doc(self::ORG_OTHER, 'Other org doc');
    }

    protected function tearDown(): void
    {
        $this->siteProp->setValue(null, $this->siteWas);
        $this->sourcesProp->setValue(null, $this->sourcesWas);
        parent::tearDown();
    }

    private function doc(string $org, string $title): void
    {
        (new Tiger_Model_Page())->insert([
            'org_id' => $org, 'type' => Docs_Model_Docs::TYPE_DOC, 'slug' => 'guide/setup', 'locale' => 'en',
            'title' => $title, 'body' => '<p>' . $title . '</p>', 'format' => 'html', 'status' => 'published',
        ]);
    }

    private function resolveFor(string $org): ?array
    {
        $docs = $this->bare(Docs_Model_Docs::class);
        return $docs->resolve('setup', 'en', $org, 'guide', Docs_Model_Docs::VIS_PUBLIC);
    }

    /** The org the public controller hands the model. */
    private function publicOrg(): string
    {
        return (string) $this->call($this->bare(Docs_IndexController::class), '_docOrgId');
    }

    /** The org the admin controller hands the model. */
    private function adminOrg(): string
    {
        return (string) $this->call($this->bare(Docs_AdminController::class), '_docOrgId');
    }

    public function testPublicServesTheSiteOrgsOverrideNotTheVisitorsOrg(): void
    {
        Tiger_Model_Org::setSiteOrgId(self::ORG_SITE);
        $this->login(self::ORG_OTHER);   // a signed-in visitor from another org browses the public site

        $this->assertSame(self::ORG_SITE, $this->publicOrg(), 'public reads resolve the SITE org');
        $doc = $this->resolveFor($this->publicOrg());
        $this->assertNotNull($doc);
        $this->assertSame('Site org doc', $doc['title']);
        $this->assertSame('db', $doc['source']);
    }

    public function testPublicFallsBackToTheSharedDocWhenTheSiteOrgHasNoOverride(): void
    {
        Tiger_Model_Org::setSiteOrgId(self::ORG_EMPTY);

        $doc = $this->resolveFor($this->publicOrg());
        $this->assertNotNull($doc);
        $this->assertSame('Shared doc', $doc['title'], 'legacy/global \'\' docs still resolve');
    }

    public function testAdminResolvesUnderTheCallersOrg(): void
    {
        $this->login(self::ORG_OTHER);
        $this->assertSame(self::ORG_OTHER, $this->adminOrg());
        $this->assertSame('Other org doc', $this->resolveFor($this->adminOrg())['title']);

        $this->login(self::ORG_EMPTY);
        $this->assertSame('Shared doc', $this->resolveFor($this->adminOrg())['title']);
    }

    public function testAdminWithNoIdentityHasNoOrg(): void
    {
        $this->assertSame('', $this->adminOrg());
    }

    public function testAnotherOrgsOverrideIsNeverServed(): void
    {
        foreach ([self::ORG_SITE, self::ORG_EMPTY] as $org) {
            $this->assertNotSame('Other org doc', $this->resolveFor($org)['title'], "org $org must not see another org's doc");
        }
    }
}
