<?php
// For skins configs

// https://www.mediawiki.org/wiki/Skin:Minerva_Neue
wfLoadSkin( 'MinervaNeue' );
// https://www.mediawiki.org/wiki/Skin:MonoBook
wfLoadSkin( 'MonoBook' );
// https://www.mediawiki.org/wiki/Skin:Timeless
wfLoadSkin( 'Timeless' );
// https://www.mediawiki.org/wiki/Skin:Vector
wfLoadSkin( 'Vector' );
// https://www.mediawiki.org/wiki/Skin:Citizen
wfLoadSkin( 'Citizen' );
$wgCitizenEnablePreferences = true;
$wgCitizenSearchDescriptionSource = "wikidata";

// https://www.mediawiki.org/wiki/Manual:$wgDefaultSkin
$wgDefaultSkin = "Citizen";

// https://www.mediawiki.org/wiki/Manual:$wgEdititis
$wgEdititis = true;

// https://www.mediawiki.org/wiki/Manual:$wgAllowUserCss
$wgAllowUserCss = true;
