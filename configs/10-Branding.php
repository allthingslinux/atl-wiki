<?php
/**
 * Wiki Branding Configuration
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

//######################################################// Logos and Icons

// https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgLogos
$wgLogos = [
    '1x' => "https://images.atl.wiki/Logo.png",
    'icon' => "https://images.atl.wiki/Logo.png",
    'svg' => "https://images.atl.wiki/Logo.svg",
];

// https://www.mediawiki.org/wiki/Manual:$wgFavicon
$wgFavicon = "https://images.atl.wiki/Logo.png";

// https://www.mediawiki.org/wiki/Manual:$wgFooterIcons
$wgFooterIcons = [
    "copyright" => [
        "copyright" => [
            "src" => "https://images.atl.wiki/Badge-ccbysa.svg",
            "url" => "https://creativecommons.org/licenses/by-sa/4.0/",
            "alt" => "Creative Commons Attribution-ShareAlike 4.0 International"
        ],
    ],
    "poweredby" => [
        "mediawiki" => [
            "src" => "https://images.atl.wiki/Badge-mediawiki.svg",
            "url" => "https://www.mediawiki.org/",
            "alt" => "Powered by MediaWiki",
        ]
    ],
];

//######################################################// CC License

// https://www.mediawiki.org/wiki/Manual:$wgRightsUrl
$wgRightsUrl ="https://creativecommons.org/licenses/by-sa/4.0/";

// https://www.mediawiki.org/wiki/Manual:$wgRightsText
$wgRightsText = "CC BY-SA 4.0";
