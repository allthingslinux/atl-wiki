<?php
/**
 * The Mediawiki Hooks Configuration
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

$wgHooks['SkinTemplateNavigation::Universal'][] = function ($skin, &$links) {
    foreach ($links as &$group) {
        foreach ($group as &$tab) {
            if (isset($tab['href'])) {
                $tab['href'] = preg_replace_callback(
                    '#/index\.php\?title=([^&]+)(&(.*))?#',
                    function ($matches) {
                        $title = $matches[1];
                        $query = isset($matches[3]) ? "?{$matches[3]}" : '';
                        return "/{$title}{$query}";
                    },
                    $tab['href']
                );
            }
        }
    }
    return true;
};
