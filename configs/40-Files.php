<?php
// For images and uploads config: https://www.mediawiki.org/wiki/Manual:Configuring_file_uploads#Configuring_file_types

// https://www.mediawiki.org/wiki/Manual:$wgEnableUploads
$wgEnableUploads = true;

// https://www.mediawiki.org/wiki/Manual:$wgUseImageMagick
$wgUseImageMagick = true;

// https://www.mediawiki.org/wiki/Manual:$wgImageMagickConvertCommand
$wgImageMagickConvertCommand = "/usr/bin/convert";

// https://www.mediawiki.org/wiki/Manual:$wgHashedUploadDirectory
$wgHashedUploadDirectory = false;

// https://www.mediawiki.org/wiki/Manual:$wgUseInstantCommons
$wgUseInstantCommons = true;

// https://www.mediawiki.org/wiki/Manual:$wgFileExtensions
$wgFileExtensions = [ 'png', 'gif', 'jpg', 'jpeg', 'doc',
	'xls', 'mpp', 'pdf', 'ppt', 'tiff', 'bmp', 'docx', 'xlsx',
	'pptx', 'ps', 'odt', 'ods', 'odp', 'odg', 'svg'
];

// https://www.mediawiki.org/wiki/Manual:$wgTrustedMediaFormats
$wgTrustedMediaFormats = [
	MEDIATYPE_BITMAP, # All bitmap formats
	MEDIATYPE_AUDIO, # All audio formats
	MEDIATYPE_VIDEO, # All plain video formats
	"image/svg+xml", # svg
	"application/pdf", # PDF
];

// https://www.mediawiki.org/wiki/Manual:$wgSVGConverters
$wgSVGConverters = [
	'rsvg' => '/usr/bin/rsvg-convert -w $width -h $height -o $output $input',
];

// https://www.mediawiki.org/wiki/Manual:$wgSVGConverter
$wgSVGConverter = 'rsvg';

// https://www.mediawiki.org/wiki/Manual:$wgMaxShellMemory
$wgMaxShellMemory = 524288;

// https://www.mediawiki.org/wiki/Manual:$wgNativeImageLazyLoading
$wgNativeImageLazyLoading = false;
