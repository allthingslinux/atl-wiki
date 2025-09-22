<?php
/**
 * Extensions Loading
 *
 * PHP version 8.3
 *
 * @category Configuration
 * @package  ATL-Wiki
 * @author   Atmois <atmois@allthingslinux.org>
 * @license  https://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 * @link     https://atl.wiki
 */

// https://www.mediawiki.org/wiki/Extension:AbuseFilter
// wfLoadExtension('AbuseFilter');
// https://www.mediawiki.org/wiki/Extension:AntiSpoof
wfLoadExtension('AntiSpoof');
// https://www.mediawiki.org/wiki/Extension:AWS
wfLoadExtension('AWS');
// https://www.mediawiki.org/wiki/Extension:BulkBlock
wfLoadExtension('BulkBlock');
// https://www.mediawiki.org/wiki/Extension:Capiunto
wfLoadExtension('Capiunto');
// https://www.mediawiki.org/wiki/Extension:CategoryTree
wfLoadExtension('CategoryTree');
// https://www.mediawiki.org/wiki/Extension:CheckUser
wfLoadExtension('CheckUser');
// https://www.mediawiki.org/wiki/Extension:Cite
wfLoadExtension('Cite');
// https://www.mediawiki.org/wiki/Extension:CiteThisPage
wfLoadExtension('CiteThisPage');
// https://www.mediawiki.org/wiki/Extension:CodeEditor
wfLoadExtension('CodeEditor');
// https://www.mediawiki.org/wiki/Extension:CodeMirror
wfLoadExtension('CodeMirror');
// https://www.mediawiki.org/wiki/Extension:ConfirmEdit
wfLoadExtensions([ 'ConfirmEdit', 'ConfirmEdit/Turnstile' ]);
// https://www.mediawiki.org/wiki/Extension:ConsoleOutput
wfLoadExtension('ConsoleOutput');
// https://www.mediawiki.org/wiki/Extension:Description2
wfLoadExtension('Description2');
// https://www.mediawiki.org/wiki/Extension:Discord
wfLoadExtension('Discord');
// https://www.mediawiki.org/wiki/Extension:DiscussionTools
wfLoadExtension('DiscussionTools');
// https://www.mediawiki.org/wiki/Extension:Drafts
wfLoadExtension('Drafts');
// https://www.mediawiki.org/wiki/Extension:Echo
wfLoadExtension('Echo');
// https://www.mediawiki.org/wiki/Extension:Editcount
wfLoadExtension('Editcount');
// https://www.mediawiki.org/wiki/Extension:FilterSpecialPages
wfLoadExtension('FilterSpecialPages');
// https://www.mediawiki.org/wiki/Extension:Gadgets
wfLoadExtension('Gadgets');
// https://www.mediawiki.org/wiki/Extension:ImageMap
wfLoadExtension('ImageMap');
// https://www.mediawiki.org/wiki/Extension:InputBox
wfLoadExtension('InputBox');
// https://www.mediawiki.org/wiki/Extension:LastModified
wfLoadExtension('LastModified');
// https://www.mediawiki.org/wiki/Extension:Linter
wfLoadExtension('Linter');
// https://www.mediawiki.org/wiki/Extension:LoginNotify
wfLoadExtension('LoginNotify');
// https://www.mediawiki.org/wiki/Extension:Math
wfLoadExtension('Math');
// https://www.mediawiki.org/wiki/Extension:MultimediaViewer
wfLoadExtension('MultimediaViewer');
// https://www.mediawiki.org/wiki/Extension:Nuke
wfLoadExtension('Nuke');
// https://www.mediawiki.org/wiki/Extension:OATHAuth
wfLoadExtension('OATHAuth');
// https://www.mediawiki.org/wiki/Extension:OAuth
wfLoadExtension('OAuth');
// https://www.mediawiki.org/wiki/Extension:OpenGraphMeta
wfLoadExtension('OpenGraphMeta');
// https://www.mediawiki.org/wiki/Extension:OpenID_Connect
wfLoadExtension('OpenIDConnect');
// https://www.mediawiki.org/wiki/Extension:PageImages
wfLoadExtension('PageImages');
// https://www.mediawiki.org/wiki/Extension:ParserFunctions
wfLoadExtension('ParserFunctions');
// https://www.mediawiki.org/wiki/Extension:PdfHandler
wfLoadExtension('PdfHandler');
// https://www.mediawiki.org/wiki/Extension:PluggableAuth
wfLoadExtension('PluggableAuth');
// https://www.mediawiki.org/wiki/Extension:Poem
wfLoadExtension('Poem');
// https://www.mediawiki.org/wiki/Extension:ReplaceText
wfLoadExtension('ReplaceText');
// https://www.mediawiki.org/wiki/Extension:Scribunto
wfLoadExtension('Scribunto');
// https://www.mediawiki.org/wiki/Extension:SecureLinkFixer
wfLoadExtension('SecureLinkFixer');
// https://www.mediawiki.org/wiki/Extension:SiteMetrics
wfLoadExtension('SiteMetrics');
// https://www.mediawiki.org/wiki/Extension:SpamBlacklist
wfLoadExtension('SpamBlacklist');
// https://www.mediawiki.org/wiki/Extension:SyntaxHighlight_GeSHi
wfLoadExtension('SyntaxHighlight_GeSHi');
// https://www.mediawiki.org/wiki/Extension:TemplateData
wfLoadExtension('TemplateData');
// https://www.mediawiki.org/wiki/Extension:TemplateSandbox
wfLoadExtension('TemplateSandbox');
// https://www.mediawiki.org/wiki/Extension:TemplateStyles
wfLoadExtension('TemplateStyles');
// https://www.mediawiki.org/wiki/Extension:TemplateStylesExtender
wfLoadExtension('TemplateStylesExtender');
// https://www.mediawiki.org/wiki/Extension:TextExtracts
wfLoadExtension('TextExtracts');
// https://www.mediawiki.org/wiki/Extension:Thanks
wfLoadExtension('Thanks');
// https://www.mediawiki.org/wiki/Extension:UserMerge
wfLoadExtension('UserMerge');
// https://www.mediawiki.org/wiki/Extension:VisualEditor
wfLoadExtension('VisualEditor');
// https://www.mediawiki.org/wiki/Extension:WebAuthn
wfLoadExtension('WebAuthn');
// https://www.mediawiki.org/wiki/Extension:WikiEditor
wfLoadExtension('WikiEditor');
