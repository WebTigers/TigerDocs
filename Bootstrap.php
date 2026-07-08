<?php
/**
 * Docs module bootstrap.
 *
 * A first-party, INSTALLABLE Tiger module (the first canary for the Module Installer): it
 * ships in its own public repo (WebTigers/tiger-docs) and installs into
 * application/modules/docs/. Purely additive — auto-discovered by ZF1's module scan, it
 * registers a public /docs surface without touching any core file.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Docs_Service_* (services/) load by convention; controllers load via the registered module
 * dir; configs/acl.ini + languages/ are picked up by the core globs.
 */
class Docs_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
