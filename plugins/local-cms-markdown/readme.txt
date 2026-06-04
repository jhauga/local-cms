=== Local CMS Markdown ===
Contributors: localcms
Tags: markdown, gfm, marked, mathjax, converter
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Client-side Markdown rendering for posts and pages, ported from the Local CMS theme.

== Description ==

Local CMS Markdown renders Markdown in the browser using the Local CMS conversion
engine. Toggle "Render this content as Markdown" on any post or page, or wrap a
snippet in the [localcms_markdown] shortcode.

Features:

* Built-in Local CMS renderer or marked.js (GFM)
* GitHub-style alerts, tables, task lists, footnotes, fenced code
* Local CMS HTML-template and wrapper comments
* Automatic MathJax loading when math is present
* Assets load only on views that actually use Markdown

== Installation ==

1. Extract the plugin zip into wp-content/plugins/local-cms-markdown (the zip's root
   is the plugin's files, with no wrapping folder).
2. Activate "Local CMS Markdown".
3. Edit a post or page, tick "Render this content as Markdown" in the Local CMS
   Markdown box, choose a renderer, and write Markdown.

== Frequently Asked Questions ==

= Does conversion happen on the server? =
No. The plugin emits the raw Markdown in a wrapper element and the bundled engine
converts it in the browser, identical to the Local CMS theme.

= Can I render only part of a page as Markdown? =
Yes. Use the [localcms_markdown]...[/localcms_markdown] shortcode. Add
renderer="marked" to use marked.js for that block.

== Changelog ==

= 1.0.0 =
* Initial release. Packages the Local CMS Markdown engine as a stand-alone plugin.
