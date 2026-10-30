<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Docs_Form_Settings — the docs module's admin settings: the public route override.
 *
 * Values are written to the `config` table (scope=global) by Docs_Service_Settings as
 * tiger.routing.override.docs.* — the live-override tier, effective next request, no deploy
 * (config-discipline: the config store, not a settings table). See ROUTING.md.
 *
 * @api
 */
class Docs_Form_Settings extends Tiger_Form
{
    protected function elements(): array
    {
        $control = ['class' => 'form-control'];

        return [
            ['checkbox', 'route_enabled', [
                'attribs' => ['id' => 'docs-route-enabled', 'class' => 'form-check-input'],
            ]],
            ['text', 'route_pattern', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                // A public path prefix: lowercase, may be nested (kb/help). The remainder of a
                // URL becomes the doc `slug`. The registry additionally refuses reserved heads
                // (api/auth/admin), so those can't be claimed even if entered.
                'validators' => [['Regex', true, ['pattern' => '#^[a-z0-9][a-z0-9/_-]*$#', 'messages' => [Zend_Validate_Regex::NOT_MATCH => 'Use a lowercase path like "docs" or "help".']]]],
                'attribs'    => array_merge($control, ['id' => 'docs-route-pattern', 'placeholder' => 'docs']),
            ]],
            ['text', 'route_priority', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits']],
                'attribs'    => array_merge($control, ['id' => 'docs-route-priority', 'inputmode' => 'numeric']),
            ]],
            // The content source: one URL every server fetches the manual from. Optional — blank
            // means the shipped/module content serves, which is every ordinary install.
            // https only, because the files it returns are rendered into every docs page.
            ['text', 'content_url', [
                'required'   => false,
                'allowEmpty' => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', true, ['pattern' => '#^https://[A-Za-z0-9.-]+(?::\d+)?(?:/[A-Za-z0-9._~/-]*)?$#', 'messages' => [Zend_Validate_Regex::NOT_MATCH => 'Use an https URL, e.g. https://cdn.example.com/docs.']]]],
                'attribs'    => array_merge($control, ['id' => 'docs-content-url', 'placeholder' => 'https://cdn.example.com/docs']),
            ]],
        ];
    }
}
