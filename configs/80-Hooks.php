<?php
/**
 * The Mediawiki Hooks Configuration
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://atl.wiki
 */

$wgHooks['SkinTemplateNavigation::Universal'][] = function ( $skin, &$links ) {
    foreach ( $links as &$group ) {
        foreach ( $group as &$tab ) {
            if (isset($tab['href']) ) {
                $tab['href'] = preg_replace_callback(
                    '#/index\.php\?title=([^&]+)(&(.*))?#',
                    function ( $matches ) {
                        $title = $matches[1];
                        $query = isset($matches[3]) ? '?' . $matches[3] : '';
                        return '/' . $title . $query;
                    },
                    $tab['href']
                );
            }
        }
    }
    return true;
};
