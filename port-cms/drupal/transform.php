<?php
declare(strict_types=1);

/**
 * Drupal adapter for port-cms.
 *
 * Converts a staged Local CMS (WordPress-shaped) theme or plugin into a
 * Drupal 9/10/11 theme or module scaffold, rewriting <staging_dir> in place.
 *
 *   php transform.php <themes|plugins> <name> <staging_dir>
 *
 * port-cms invokes this (via transform.sh / transform.bat) after staging and
 * before archiving, so whatever is left in <staging_dir> is what gets packaged.
 * The original WordPress files are preserved under _wordpress-source/ for
 * reference while the markup is ported to Twig.
 */

[$self, $tool, $name, $stage] = array_pad($argv, 4, null);

if (!$tool || !$stage || !is_dir((string) $stage)) {
    fwrite(STDERR, "[drupal] usage: php transform.php <themes|plugins> <name> <staging_dir>\n");
    exit(1);
}

$stage = rtrim((string) $stage, "/\\");
$slug = basename($stage);                  // local-cms, local-cms-markdown, ...
$machine = drupal_machine_name($slug);     // local_cms, local_cms_markdown

switch ($tool) {
    case 'themes':
        port_theme($stage, $machine);
        break;
    case 'plugins':
        port_module($stage, $machine, $name);
        break;
    default:
        fwrite(STDERR, "[drupal] unknown tool '{$tool}'.\n");
        exit(1);
}

echo "[drupal] Scaffolded Drupal " . ($tool === 'themes' ? 'theme' : 'module') . " '{$machine}'.\n";
exit(0);

// ---------------------------------------------------------------------------

/** Sanitize a slug into a valid Drupal machine name (starts with a letter). */
function drupal_machine_name(string $slug): string
{
    $m = preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug));
    $m = trim((string) preg_replace('/_+/', '_', (string) $m), '_');
    if ($m === '' || !preg_match('/^[a-z]/', $m)) {
        $m = 'local_cms' . ($m !== '' ? '_' . $m : '');
    }
    return $m;
}

/** Quote a scalar for YAML, escaping embedded single quotes. */
function yq(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/** Pull "Key: Value" fields out of a WordPress style.css / plugin header. */
function wp_header(string $content, array $keys): array
{
    $found = [];
    foreach ($keys as $key) {
        $pattern = '/^[\s*]*' . preg_quote($key, '/') . '\s*:\s*(.+?)\s*$/mi';
        if (preg_match($pattern, $content, $m)) {
            $found[$key] = trim($m[1]);
        }
    }
    return $found;
}

/** Move a top-level file/dir of the stage into _wordpress-source/. */
function archive_source(string $stage, string $entry): void
{
    $from = $stage . '/' . $entry;
    if (!file_exists($from)) {
        return;
    }
    $ref = $stage . '/_wordpress-source';
    if (!is_dir($ref)) {
        mkdir($ref, 0777, true);
    }
    rename($from, $ref . '/' . basename($entry));
}

function put(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $contents);
}

// ---------------------------------------------------------------------------

function port_theme(string $stage, string $machine): void
{
    $cssHeader = is_file($stage . '/style.css') ? (string) file_get_contents($stage . '/style.css') : '';
    $h = wp_header($cssHeader, ['Theme Name', 'Description', 'Version', 'Author', 'License']);

    $name = $h['Theme Name'] ?? 'Local CMS';
    $description = $h['Description'] ?? 'Ported from the Local CMS WordPress theme.';
    $version = $h['Version'] ?? '1.0.0';

    // Relocate the stylesheet to Drupal's conventional css/ folder.
    if (is_file($stage . '/style.css')) {
        put($stage . '/css/style.css', (string) file_get_contents($stage . '/style.css'));
        unlink($stage . '/style.css');
    }

    // Lift the inline behavior script out of footer.php into a real JS asset.
    $hasJs = extract_footer_js($stage);

    // Port any optional WordPress templates the staged theme ships (search,
    // sidebar, 404). Returns which were found so the .info.yml/.theme wiring
    // below can react. Themes without these (e.g. the default theme) are
    // unaffected; richer themes (e.g. local-builder) get them ported too.
    $optional = port_optional_templates($stage);

    // info.yml
    $info = "name: " . yq($name) . "\n"
        . "type: theme\n"
        . "base theme: stable9\n"
        . "description: " . yq($description) . "\n"
        . "package: 'Local CMS'\n"
        . "core_version_requirement: ^9 || ^10 || ^11\n"
        . "version: " . yq($version) . "\n"
        . "libraries:\n"
        . "  - '{$machine}/global-styling'\n"
        . "regions:\n"
        . "  header: 'Header'\n"
        . "  primary_menu: 'Primary menu'\n"
        . "  content: 'Content'\n"
        . "  sidebar: 'Sidebar'\n"
        . "  footer: 'Footer'\n";
    put($stage . "/{$machine}.info.yml", $info);

    // libraries.yml — global styling, plus the lifted behavior script if present.
    $libraries = "global-styling:\n"
        . "  version: VERSION\n"
        . "  css:\n"
        . "    theme:\n"
        . "      css/style.css: {}\n";
    if ($hasJs) {
        $libraries .= "  js:\n"
            . "    js/theme.js: {}\n";
    }
    put($stage . "/{$machine}.libraries.yml", $libraries);

    // Theme hooks: expose site name/slogan to page.html.twig for the footer line.
    // When a 404.php was ported, append the suggestion hook that activates
    // templates/page--404.html.twig on 404 responses.
    $themeHooks = theme_dot_theme($machine, $name);
    if (!empty($optional['has_404'])) {
        $themeHooks .= theme_page_suggestions_hook($machine);
    }
    put($stage . "/{$machine}.theme", $themeHooks);

    // Twig templates. The original WordPress class names are preserved so the
    // ported css/style.css applies directly (the "Twig approach" to class
    // reconciliation): page/node carry the layout classes, and the menu and
    // branding overrides re-emit the navigation/branding classes.
    // html.html.twig is intentionally NOT overridden: the document shell is
    // inherited from the stable9 base theme, which is proven and avoids the
    // strict_variables pitfalls of a hand-rolled shell.
    put($stage . '/templates/page.html.twig', theme_page_twig());
    put($stage . '/templates/node.html.twig', theme_node_twig());
    put($stage . '/templates/node--teaser.html.twig', theme_node_teaser_twig());
    put($stage . '/templates/menu--main.html.twig', theme_menu_main_twig());
    put($stage . '/templates/menu--footer.html.twig', theme_menu_footer_twig());
    put($stage . '/templates/block--system-branding-block.html.twig', theme_branding_twig());

    // Default block placements. A new Drupal theme starts with no blocks, which
    // leaves the site unusable (no menu, no Edit tabs, no page content). These
    // place the branding, main menu, and the essential system blocks so the
    // theme works on install, the way core themes ship config/install blocks.
    foreach (theme_blocks($machine) as $id => $yaml) {
        put($stage . "/config/install/block.block.{$id}.yml", $yaml);
    }

    // Preserve the WordPress-shaped source for reference while porting markup.
    foreach (['index.php', 'page.php', 'single.php', 'archive.php', 'header.php',
              'footer.php', 'functions.php', 'theme.json', 'template-parts',
              'search.php', 'searchform.php', 'sidebar.php', '404.php', 'comments.php'] as $entry) {
        archive_source($stage, $entry);
    }

    put($stage . '/_wordpress-source/README.md', source_reference_note('theme'));
}

/**
 * Extract the inline <script> from footer.php into js/theme.js.
 *
 * The WordPress theme ships its sticky-header behavior as an inline script in
 * footer.php. Drupal attaches JS through libraries, so lift the script body
 * into a standalone asset. Returns true when a script was written.
 */
function extract_footer_js(string $stage): bool
{
    $footer = $stage . '/footer.php';
    if (!is_file($footer)) {
        return false;
    }
    if (!preg_match('/<script\b[^>]*>(.*?)<\/script>/is', (string) file_get_contents($footer), $m)) {
        return false;
    }
    $body = dedent(trim($m[1], "\r\n"));
    if (trim($body) === '') {
        return false;
    }
    $banner = "/**\n * Ported from the Local CMS theme footer.php inline script.\n"
        . " * Fixes the site header on scroll for wide viewports.\n */\n";
    put($stage . '/js/theme.js', $banner . $body . "\n");
    return true;
}

/** Remove the common leading indentation shared by every non-blank line. */
function dedent(string $text): string
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $min = null;
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $indent = strlen($line) - strlen(ltrim($line, " \t"));
        $min = $min === null ? $indent : min($min, $indent);
    }
    if (!$min) {
        return $text;
    }
    return implode("\n", array_map(
        static fn(string $line): string => substr($line, $min) === false ? $line : substr($line, $min),
        $lines
    ));
}

/**
 * Port the optional WordPress templates a theme may ship beyond the core set.
 *
 * Data-driven on purpose: each entry maps a staged WordPress file to the Twig
 * artifact it produces, written only when the source file is present. The map is
 * the single place to teach this adapter a new template, and a sibling CMS
 * adapter can mirror the same "emit when present" shape for its own conventions
 * instead of duplicating per-file branching.
 *
 * Returns flags describing what was found so the caller can wire the matching
 * .info.yml regions and .theme hooks (e.g. the page__404 suggestion).
 *
 * @return array{has_search: bool, has_sidebar: bool, has_404: bool}
 */
function port_optional_templates(string $stage): array
{
    // source file => [target twig (relative to stage), generator function].
    // searchform.php and search.php both resolve to the one search-block
    // override; writing it once is enough, so already-written targets are skipped.
    $map = [
        'searchform.php' => ['templates/block--search-form-block.html.twig', 'theme_search_block_twig'],
        'search.php'     => ['templates/block--search-form-block.html.twig', 'theme_search_block_twig'],
        'sidebar.php'    => ['templates/region--sidebar.html.twig', 'theme_region_sidebar_twig'],
        '404.php'        => ['templates/page--404.html.twig', 'theme_page_404_twig'],
    ];

    $written = [];

    foreach ($map as $source => [$target, $generator]) {
        if (!is_file($stage . '/' . $source) || isset($written[$target])) {
            continue;
        }
        put($stage . '/' . $target, $generator());
        $written[$target] = true;
    }

    return [
        'has_search' => is_file($stage . '/searchform.php') || is_file($stage . '/search.php'),
        'has_sidebar' => is_file($stage . '/sidebar.php'),
        'has_404' => is_file($stage . '/404.php'),
    ];
}

function port_module(string $stage, string $machine, ?string $name): void
{
    // Find the plugin's main file (the one carrying the WordPress plugin header).
    $mainFile = null;
    $header = '';
    foreach (glob($stage . '/*.php') ?: [] as $php) {
        $contents = (string) file_get_contents($php);
        if (stripos($contents, 'Plugin Name:') !== false) {
            $mainFile = $php;
            $header = $contents;
            break;
        }
    }
    $h = wp_header($header, ['Plugin Name', 'Description', 'Version']);

    $label = $h['Plugin Name'] ?? 'Local CMS Markdown';
    $description = $h['Description'] ?? 'Ported from the Local CMS WordPress plugin.';
    $version = $h['Version'] ?? '1.0.0';

    // info.yml
    $info = "name: " . yq($label) . "\n"
        . "type: module\n"
        . "description: " . yq($description) . "\n"
        . "package: 'Local CMS'\n"
        . "core_version_requirement: ^9 || ^10 || ^11\n"
        . "version: " . yq($version) . "\n";
    put($stage . "/{$machine}.info.yml", $info);

    // libraries.yml — the engine, plus templates.js which seeds the templates
    // global from drupalSettings before convert.js reads it.
    $hasCss = is_file($stage . '/assets/markdown.css');
    $libraries = "markdown:\n  version: VERSION\n  js:\n"
        . "    js/templates.js: {}\n"
        . "    assets/convert.js: {}\n";
    if ($hasCss) {
        $libraries .= "  css:\n    theme:\n      assets/markdown.css: {}\n";
    }
    $libraries .= "  dependencies:\n    - core/drupalSettings\n";
    put($stage . "/{$machine}.libraries.yml", $libraries);
    put($stage . '/js/templates.js', module_templates_js());

    // .module — add the localcms-theme body class so the engine activates.
    put($stage . "/{$machine}.module", module_php($machine, $label));

    // Filter plugin — wraps field text in [data-markdown-body] and attaches the
    // engine + templates, the Drupal counterpart of the plugin's render toggle.
    put($stage . "/src/Plugin/Filter/LocalCmsMarkdownFilter.php", module_filter_php($machine));

    // Templating system: admin form + storage + default templates, mirroring the
    // WordPress plugin's Templating screen and Local CMS's /admin/templating.
    put($stage . "/src/Form/TemplatingForm.php", module_templating_form_php($machine));
    put($stage . "/{$machine}.routing.yml", module_routing_yml($machine));
    put($stage . "/{$machine}.links.menu.yml", module_menu_links_yml($machine));
    put($stage . "/{$machine}.permissions.yml", module_permissions_yml($machine));
    put($stage . "/config/install/{$machine}.settings.yml", module_settings_install_yml());
    put($stage . "/config/schema/{$machine}.schema.yml", module_settings_schema_yml($machine, $label));

    // Ship a ready-to-use text format so the filter is usable on install: pick
    // "Local CMS Markdown" as a field's format and the engine renders it.
    put($stage . "/config/install/filter.format.{$machine}.yml", module_text_format($machine, $label));

    // Preserve the WordPress source for reference.
    foreach (glob($stage . '/*.php') ?: [] as $php) {
        if (basename($php) === "{$machine}.module") {
            continue;
        }
        archive_source($stage, basename($php));
    }
    archive_source($stage, 'readme.txt');

    put($stage . '/_wordpress-source/README.md', source_reference_note('module'));
}

// --- Generated file bodies -------------------------------------------------

function theme_page_twig(): string
{
    return <<<'TWIG'
{#
  Ported from the Local CMS header.php + footer.php. The original WordPress
  class names are preserved so css/style.css applies directly. Branding and
  navigation are rendered through Drupal blocks placed in these regions:
    - header        -> "Site branding" block
    - primary_menu  -> "Main navigation" block
    - footer        -> "Footer" menu block
  The block--system-branding-block and menu--* overrides re-emit the matching
  WordPress classes.
#}
<div class="page-shell">
  <div class="site-header-shell" data-site-header-shell>
    <header class="site-header" data-site-header role="banner">
      {{ page.header }}
      {% if page.primary_menu %}
        <nav class="site-nav" aria-label="{{ 'Primary navigation'|t }}">
          {{ page.primary_menu }}
        </nav>
      {% endif %}
    </header>
  </div>

  <main id="content" class="site-main" role="main">
    {{ page.content }}
  </main>

  {% if page.sidebar %}
    <aside class="site-sidebar" role="complementary">
      {{ page.sidebar }}
    </aside>
  {% endif %}

  <footer class="site-footer" role="contentinfo">
    <div class="footer-copy">
      <p class="footer-heading">{{ 'Built to migrate cleanly.'|t }}</p>
    </div>
    {% if page.footer %}
      <nav class="footer-nav" aria-label="{{ 'Footer navigation'|t }}">
        {{ page.footer }}
      </nav>
    {% endif %}
    <p class="footer-meta">&copy; {{ 'now'|date('Y') }}{% if site_name %} {{ site_name }}{% endif %}</p>
  </footer>
</div>

TWIG;
}

function theme_node_twig(): string
{
    // Ported from template-parts/content-article.php (full post/page view).
    return <<<'TWIG'
{#
  Full node view, ported from template-parts/content-article.php. WordPress
  classes are preserved so css/style.css applies. {{ content }} holds the body
  markup; {{ label }} is the title.
#}
<article{{ attributes.addClass('content-panel', 'entry-shell') }}>
  <div class="entry-grid">
    <div class="entry-main">
      <p class="eyebrow">{{ node.bundle == 'page' ? 'Page'|t : 'Post'|t }}</p>
      {{ title_prefix }}
      {% if label %}
        <h1{{ title_attributes }}>{{ label }}</h1>
      {% endif %}
      {{ title_suffix }}
      {% if display_submitted %}
        <div class="meta-row">
          <p class="story-meta">{{ date }}</p>
          <p class="story-meta">{{ 'By'|t }} {{ author_name }}</p>
        </div>
      {% endif %}
      <div class="prose">
        {{ content }}
      </div>
    </div>
  </div>
</article>

TWIG;
}

function theme_node_teaser_twig(): string
{
    // Ported from template-parts/post-card.php (archive / listing card).
    return <<<'TWIG'
{#
  Teaser node view, ported from template-parts/post-card.php. Used for the
  listing cards in the story grid.
#}
<article{{ attributes.addClass('story-card') }}>
  <a class="story-link-wrap" href="{{ url }}">
    <div class="story-copy">
      {% if display_submitted %}
        <div class="meta-row">
          <p class="story-meta">{{ date }}</p>
        </div>
      {% endif %}
      <h3 class="story-title">{{ label }}</h3>
    </div>
  </a>
</article>

TWIG;
}

function theme_menu_main_twig(): string
{
    // Re-emits the WordPress site-nav link classes (.nav-link / .is-active).
    // Uses the link() function (as core menu.html.twig does) because item.url
    // is a \Drupal\Core\Url object and cannot be printed as a string directly.
    return <<<'TWIG'
{% import _self as menus %}
{{ menus.links(items) }}
{% macro links(items) %}
  {% import _self as menus %}
  {% for item in items %}
    {{ link(item.title, item.url, { 'class': ['nav-link', item.in_active_trail ? 'is-active' : ''] }) }}
    {% if item.below %}{{ menus.links(item.below) }}{% endif %}
  {% endfor %}
{% endmacro %}

TWIG;
}

function theme_menu_footer_twig(): string
{
    // Re-emits the WordPress footer-nav link class (.footer-link). Uses link()
    // for the same reason as menu--main (item.url is a Url object).
    return <<<'TWIG'
{% import _self as menus %}
{{ menus.links(items) }}
{% macro links(items) %}
  {% import _self as menus %}
  {% for item in items %}
    {{ link(item.title, item.url, { 'class': ['footer-link'] }) }}
    {% if item.below %}{{ menus.links(item.below) }}{% endif %}
  {% endfor %}
{% endmacro %}

TWIG;
}

function theme_branding_twig(): string
{
    // Re-emits the WordPress branding classes (.site-branding/.site-title/etc.).
    return <<<'TWIG'
{#
  Site branding block, ported from header.php branding. Re-emits the Local CMS
  WordPress classes so css/style.css applies.
#}
<div class="site-branding"{{ attributes }}>
  {% if site_logo %}
    <a class="site-title" href="{{ path('<front>') }}" rel="home">
      <img class="logo" src="{{ site_logo }}" alt="{{ site_name }}">
    </a>
  {% elseif site_name %}
    <a class="site-title" href="{{ path('<front>') }}" rel="home">{{ site_name }}</a>
  {% endif %}
  {% if site_slogan %}
    <p class="site-tagline">{{ site_slogan }}</p>
  {% endif %}
</div>

TWIG;
}

function theme_search_block_twig(): string
{
    // Ported from searchform.php / search.php. Re-emits the search-form wrapper
    // class so css/style.css applies; {{ content }} is the Drupal search form.
    return <<<'TWIG'
{#
  Search block, ported from the Local CMS searchform.php. Re-emits the
  widget/search-form classes so css/style.css applies. {{ content }} is the
  Drupal-rendered search form; place a "Search form" block in the sidebar region
  to use it.
#}
<section{{ attributes.addClass('widget', 'widget-search') }}>
  {{ title_prefix }}
  {% if label %}
    <h2 class="widget-title">{{ label }}</h2>
  {% endif %}
  {{ title_suffix }}
  <div class="search-form">
    {{ content }}
  </div>
</section>

TWIG;
}

function theme_region_sidebar_twig(): string
{
    // Ported from sidebar.php. Wraps the sidebar region's blocks in the
    // WordPress aside class so css/style.css applies.
    return <<<'TWIG'
{#
  Sidebar region, ported from the Local CMS sidebar.php. Wraps the blocks placed
  in the sidebar region with the site-sidebar class so css/style.css applies.
#}
{% if content %}
  <aside class="site-sidebar" role="complementary">
    {{ content }}
  </aside>
{% endif %}

TWIG;
}

function theme_page_404_twig(): string
{
    // Ported from 404.php. Activated by the page__404 suggestion added in the
    // .theme hook; mirrors the WordPress not-found layout. {{ page.content }}
    // carries Drupal's own 404 message and any blocks placed in the content
    // region (e.g. a search block).
    return <<<'TWIG'
{#
  404 page, ported from the Local CMS 404.php. Rendered for 404 responses via the
  page__404 suggestion in the .theme file. The page-shell / site-header /
  site-footer classes are preserved so css/style.css applies.
#}
<div class="page-shell">
  {% if page.header %}
    <header class="site-header" role="banner">
      {{ page.header }}
      {% if page.primary_menu %}
        <nav class="site-nav" aria-label="{{ 'Primary navigation'|t }}">
          {{ page.primary_menu }}
        </nav>
      {% endif %}
    </header>
  {% endif %}

  <main id="content" class="site-main narrow-layout" role="main">
    <article class="content-panel entry-shell">
      <div class="entry-main">
        <p class="eyebrow">{{ 'Error 404'|t }}</p>
        <h1>{{ 'Page not found'|t }}</h1>
        <p class="lead">{{ 'The page you were looking for is not here. It may have been moved, renamed, or never existed.'|t }}</p>
        {{ page.content }}
        <div class="hero-actions">
          <a class="button-link" href="{{ path('<front>') }}">{{ 'Back to home'|t }}</a>
        </div>
      </div>
    </article>
  </main>

  <footer class="site-footer" role="contentinfo">
    <p class="footer-meta">&copy; {{ 'now'|date('Y') }}{% if site_name %} {{ site_name }}{% endif %}</p>
  </footer>
</div>

TWIG;
}

function theme_page_suggestions_hook(string $machine): string
{
    return <<<PHP

/**
 * Implements hook_theme_suggestions_page_alter().
 *
 * Ported from the Local CMS 404.php. Adds a page__404 suggestion on 404
 * responses so templates/page--404.html.twig is used, the Drupal equivalent of
 * WordPress loading 404.php for unresolved URLs.
 */
function {$machine}_theme_suggestions_page_alter(array &\$suggestions, array \$variables) {
  \$exception = \\Drupal::request()->attributes->get('exception');
  if (\$exception instanceof \\Symfony\\Component\\HttpKernel\\Exception\\HttpExceptionInterface && \$exception->getStatusCode() == 404) {
    \$suggestions[] = 'page__404';
  }
}

PHP;
}

function theme_dot_theme(string $machine, string $name): string
{
    return <<<PHP
<?php

/**
 * @file
 * Theme hooks for the {$name} theme, ported from Local CMS.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_HOOK() for page templates.
 *
 * Exposes the site name and slogan to page.html.twig so the ported footer can
 * render the "(c) YEAR Site name" line from the original WordPress theme.
 */
function {$machine}_preprocess_page(array &\$variables) {
  \$config = \\Drupal::config('system.site');
  \$variables['site_name'] = \$config->get('name');
  \$variables['site_slogan'] = \$config->get('slogan');
}

PHP;
}

/**
 * Default block placements imported when the theme is installed.
 *
 * Returns [block_id => yaml]. Branding goes in the header, the main menu in
 * primary_menu, and the essential system blocks in the content region, ordered
 * by weight so they sit above the main page content. The Tabs block is what
 * restores the per-node Edit tab on the front-end theme.
 */
function theme_blocks(string $machine): array
{
    return [
        "{$machine}_branding" => block_yaml($machine, 'branding', 'header', 0, 'system_branding_block', 'Site branding', 'system', [], [
            'use_site_logo: true',
            'use_site_name: true',
            'use_site_slogan: true',
        ]),
        "{$machine}_main_menu" => block_yaml($machine, 'main_menu', 'primary_menu', 0, 'system_menu_block:main', 'Main navigation', 'system', ['system.menu.main'], [
            'level: 1',
            'depth: 0',
            'expand_all_items: false',
        ]),
        "{$machine}_messages" => block_yaml($machine, 'messages', 'content', -50, 'system_messages_block', 'Status messages', 'system'),
        "{$machine}_breadcrumbs" => block_yaml($machine, 'breadcrumbs', 'content', -40, 'system_breadcrumb_block', 'Breadcrumbs', 'system'),
        "{$machine}_page_title" => block_yaml($machine, 'page_title', 'content', -30, 'page_title_block', 'Page title', 'core'),
        "{$machine}_primary_admin_actions" => block_yaml($machine, 'primary_admin_actions', 'content', -20, 'local_actions_block', 'Primary admin actions', 'core'),
        "{$machine}_tabs" => block_yaml($machine, 'tabs', 'content', -10, 'local_tasks_block', 'Tabs', 'core', [], ['primary: true', 'secondary: true']),
        "{$machine}_content" => block_yaml($machine, 'content', 'content', 0, 'system_main_block', 'Main page content', 'system'),
    ];
}

/**
 * Build a block.block.* config entity body.
 *
 * @param string[] $configDeps   Config dependencies (e.g. a menu) beyond the theme.
 * @param string[] $extraSettings Extra "settings:" lines (already "key: value").
 */
function block_yaml(string $machine, string $suffix, string $region, int $weight, string $plugin, string $label, string $provider, array $configDeps = [], array $extraSettings = []): string
{
    $deps = "dependencies:\n";
    if ($configDeps) {
        $deps .= "  config:\n";
        foreach ($configDeps as $c) {
            $deps .= "    - {$c}\n";
        }
    }
    $deps .= "  module:\n    - system\n  theme:\n    - {$machine}\n";

    // Plugin ids with a colon (system_menu_block:main) must be quoted in YAML.
    $pluginValue = strpos($plugin, ':') !== false ? "'{$plugin}'" : $plugin;

    $settings = "settings:\n"
        . "  id: {$pluginValue}\n"
        . "  label: '{$label}'\n"
        . "  label_display: '0'\n"
        . "  provider: {$provider}\n";
    foreach ($extraSettings as $line) {
        $settings .= "  {$line}\n";
    }

    return "langcode: en\n"
        . "status: true\n"
        . $deps
        . "id: {$machine}_{$suffix}\n"
        . "theme: {$machine}\n"
        . "region: {$region}\n"
        . "weight: {$weight}\n"
        . "plugin: {$pluginValue}\n"
        . $settings
        . "visibility: {  }\n";
}

function module_php(string $machine, string $label): string
{
    return <<<PHP
<?php

/**
 * @file
 * {$label} - Drupal module ported from the Local CMS WordPress plugin.
 *
 * Provides the "Local CMS Markdown" text filter (see src/Plugin/Filter) plus the
 * client-side engine (assets/convert.js + markdown.css). convert.js only renders
 * [data-markdown-body] elements when the page body carries the localcms-theme
 * class, so this module adds that class, mirroring the WordPress plugin.
 */

use Drupal\\Core\\Routing\\RouteMatchInterface;

/**
 * Implements hook_help().
 */
function {$machine}_help(\$route_name, RouteMatchInterface \$route_match) {
  if (\$route_name === 'help.page.{$machine}') {
    return '<p>' . t('Enable the "Local CMS Markdown" filter on a text format, then author content in that format. The Local CMS engine renders the Markdown in the browser.') . '</p>';
  }
  return NULL;
}

/**
 * Implements hook_preprocess_html().
 *
 * Mirrors the WordPress theme's body.localcms-theme hook so convert.js processes
 * the [data-markdown-body] wrappers emitted by the filter.
 */
function {$machine}_preprocess_html(array &\$variables) {
  \$variables['attributes']['class'][] = 'localcms-theme';
}

PHP;
}

function module_filter_php(string $machine): string
{
    return <<<PHP
<?php

namespace Drupal\\{$machine}\\Plugin\\Filter;

use Drupal\\Component\\Utility\\Html;
use Drupal\\filter\\FilterProcessResult;
use Drupal\\filter\\Plugin\\FilterBase;

/**
 * Wraps text for client-side rendering by the Local CMS Markdown engine.
 *
 * The Markdown source is HTML-escaped inside a [data-markdown-body] element;
 * convert.js reads it back from textContent and renders it in the browser.
 *
 * Declared as TYPE_TRANSFORM_IRREVERSIBLE (not TYPE_MARKUP_LANGUAGE) on purpose:
 * the filter's own output is HTML, and the Markdown conversion happens in the
 * browser. TYPE_MARKUP_LANGUAGE would mark the format as non-HTML and make it
 * incompatible with CKEditor 5 ("CKEditor 5 only works with HTML-based text
 * formats"). With this type the filter can live on a CKEditor 5 format, and the
 * Markdown is authored through the editor's Source view.
 *
 * @Filter(
 *   id = "{$machine}",
 *   title = @Translation("Local CMS Markdown"),
 *   description = @Translation("Renders the text as Markdown in the browser. Compatible with CKEditor 5; author Markdown via the editor's Source view."),
 *   type = Drupal\\filter\\Plugin\\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE
 * )
 */
class LocalCmsMarkdownFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process(\$text, \$langcode) {
    \$markdown = \$text;

    // The text may arrive as raw Markdown (a plain-text format) or as HTML that
    // a WYSIWYG editor such as CKEditor 5 wrapped around it. When it looks like
    // editor HTML, recover the Markdown source: block tags become line breaks,
    // remaining tags are stripped, and entities are decoded. Plain-text
    // Markdown is passed through untouched so inline HTML is preserved.
    if (preg_match('#<(?:p|div|br|h[1-6]|ul|ol|li|pre|blockquote)[\\s/>]#i', \$text)) {
      \$markdown = preg_replace('#<br\\s*/?>#i', "\\n", \$markdown);
      \$markdown = preg_replace('#</(?:p|div|h[1-6]|li|pre|blockquote)>#i', "\\n\\n", \$markdown);
      \$markdown = strip_tags(\$markdown);
      \$markdown = html_entity_decode(\$markdown, ENT_QUOTES | ENT_HTML5);
      \$markdown = trim(\$markdown);
    }

    \$wrapped = '<div data-markdown-body>' . Html::escape(\$markdown) . '</div>';
    \$result = new FilterProcessResult(\$wrapped);
    \$result->addAttachments([
      'library' => ['{$machine}/markdown'],
      'drupalSettings' => ['localCmsMarkdown' => ['templates' => \$this->templateMap()]],
    ]);
    return \$result;
  }

  /**
   * The configured templates as a name => markup map for the engine.
   */
  protected function templateMap() {
    \$map = [];
    \$templates = \\Drupal::config('{$machine}.settings')->get('templates') ?: [];
    foreach (\$templates as \$template) {
      if (!empty(\$template['name']) && isset(\$template['markup'])) {
        \$map[\$template['name']] = \$template['markup'];
      }
    }
    return \$map;
  }

}

PHP;
}

function module_text_format(string $machine, string $label): string
{
    // A text format with only the Local CMS Markdown filter enabled, so authors
    // can pick "<label>" on a field and have the engine render it. Grant the
    // "use <label> text format" permission to the relevant roles after install.
    return "langcode: en\n"
        . "status: true\n"
        . "dependencies:\n"
        . "  module:\n"
        . "    - {$machine}\n"
        . "name: " . yq($label) . "\n"
        . "format: {$machine}\n"
        . "weight: 0\n"
        . "filters:\n"
        . "  {$machine}:\n"
        . "    id: {$machine}\n"
        . "    provider: {$machine}\n"
        . "    status: true\n"
        . "    weight: 0\n"
        . "    settings: {  }\n";
}

function module_templates_js(): string
{
    return <<<'JS'
/**
 * Bridges the Drupal-managed templates to the Local CMS Markdown engine.
 * convert.js reads window.LocalCmsMarkdownTemplates; seed it from drupalSettings
 * before the engine runs (convert.js reads the global on DOMContentLoaded).
 */
(function (drupalSettings) {
  'use strict';
  var settings = (drupalSettings && drupalSettings.localCmsMarkdown) || {};
  window.LocalCmsMarkdownTemplates = settings.templates || {};
})(window.drupalSettings);

JS;
}

function module_templating_form_php(string $machine): string
{
    return <<<PHP
<?php

namespace Drupal\\{$machine}\\Form;

use Drupal\\Core\\Form\\ConfigFormBase;
use Drupal\\Core\\Form\\FormStateInterface;

/**
 * Manage the reusable Markdown HTML templates.
 *
 * The Drupal counterpart of the WordPress plugin's Templating screen and Local
 * CMS's /admin/templating: each template is a name plus an HTML snippet holding
 * the {__markdown__} placeholder, stored as name => markup and exposed to the
 * engine via drupalSettings.
 */
class TemplatingForm extends ConfigFormBase {

  const SETTINGS = '{$machine}.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return '{$machine}_templating';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array \$form, FormStateInterface \$form_state) {
    \$stored = \$this->config(self::SETTINGS)->get('templates') ?: [];

    \$row_count = \$form_state->get('row_count');
    if (\$row_count === NULL) {
      \$row_count = max(count(\$stored) + 1, 2);
      \$form_state->set('row_count', \$row_count);
    }

    \$form['help'] = [
      '#markup' => '<p>' . \$this->t('Define reusable HTML wrappers. Each snippet must include the <code>{__markdown__}</code> placeholder. Call a template in Markdown with <code>&lt;!-- html:template=name --&gt;</code>. Clear a row and save to remove it.') . '</p>',
    ];

    \$form['templates'] = [
      '#type' => 'table',
      '#header' => [\$this->t('Name'), \$this->t('HTML snippet')],
      '#tree' => TRUE,
    ];

    for (\$i = 0; \$i < \$row_count; \$i++) {
      \$form['templates'][\$i]['name'] = [
        '#type' => 'textfield',
        '#title' => \$this->t('Name'),
        '#title_display' => 'invisible',
        '#default_value' => \$stored[\$i]['name'] ?? '',
        '#size' => 20,
        '#placeholder' => 'callout',
      ];
      \$form['templates'][\$i]['markup'] = [
        '#type' => 'textarea',
        '#title' => \$this->t('HTML snippet'),
        '#title_display' => 'invisible',
        '#default_value' => \$stored[\$i]['markup'] ?? '',
        '#rows' => 6,
        '#placeholder' => '<!-- html:begin --> {__markdown__} <!-- html:end -->',
      ];
    }

    \$form['add_row'] = [
      '#type' => 'submit',
      '#value' => \$this->t('Add template'),
      '#submit' => ['::addRow'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm(\$form, \$form_state);
  }

  /**
   * Submit handler: append a blank row and rebuild (no JavaScript required).
   */
  public function addRow(array &\$form, FormStateInterface \$form_state) {
    \$form_state->set('row_count', (int) \$form_state->get('row_count') + 1);
    \$form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &\$form, FormStateInterface \$form_state) {
    \$seen = [];
    foreach ((array) \$form_state->getValue('templates') as \$i => \$row) {
      \$name = trim((string) (\$row['name'] ?? ''));
      \$markup = trim((string) (\$row['markup'] ?? ''));
      if (\$name === '' && \$markup === '') {
        continue;
      }
      if (\$name === '') {
        \$form_state->setError(\$form['templates'][\$i]['name'], \$this->t('Row @n is missing a name.', ['@n' => \$i + 1]));
      }
      elseif (!preg_match('/^[A-Za-z0-9_-]+\$/', \$name)) {
        \$form_state->setError(\$form['templates'][\$i]['name'], \$this->t('Row @n: use only letters, numbers, underscores, or hyphens.', ['@n' => \$i + 1]));
      }
      elseif (isset(\$seen[\$name])) {
        \$form_state->setError(\$form['templates'][\$i]['name'], \$this->t('Row @n duplicates the name "@name".', ['@n' => \$i + 1, '@name' => \$name]));
      }
      else {
        \$seen[\$name] = TRUE;
      }
      if (\$markup === '') {
        \$form_state->setError(\$form['templates'][\$i]['markup'], \$this->t('Row @n is missing its HTML snippet.', ['@n' => \$i + 1]));
      }
      elseif (strpos(\$markup, '{__markdown__}') === FALSE) {
        \$form_state->setError(\$form['templates'][\$i]['markup'], \$this->t('Row @n must include the {__markdown__} placeholder.', ['@n' => \$i + 1]));
      }
    }
    parent::validateForm(\$form, \$form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &\$form, FormStateInterface \$form_state) {
    \$templates = [];
    foreach ((array) \$form_state->getValue('templates') as \$row) {
      \$name = trim((string) (\$row['name'] ?? ''));
      \$markup = trim((string) (\$row['markup'] ?? ''));
      if (\$name !== '' && \$markup !== '') {
        \$templates[] = ['name' => \$name, 'markup' => \$markup];
      }
    }
    \$this->config(self::SETTINGS)->set('templates', \$templates)->save();
    parent::submitForm(\$form, \$form_state);
  }

}

PHP;
}

function module_routing_yml(string $machine): string
{
    $slug = str_replace('_', '-', $machine);
    return "{$machine}.templating:\n"
        . "  path: '/admin/config/content/{$slug}'\n"
        . "  defaults:\n"
        . "    _form: 'Drupal\\{$machine}\\Form\\TemplatingForm'\n"
        . "    _title: 'Local CMS Markdown templates'\n"
        . "  requirements:\n"
        . "    _permission: 'administer {$machine}'\n";
}

function module_menu_links_yml(string $machine): string
{
    return "{$machine}.templating:\n"
        . "  title: 'Local CMS Markdown'\n"
        . "  description: 'Manage reusable Markdown HTML templates.'\n"
        . "  parent: system.admin_config_content\n"
        . "  route_name: {$machine}.templating\n";
}

function module_permissions_yml(string $machine): string
{
    return "administer {$machine}:\n"
        . "  title: 'Administer Local CMS Markdown templates'\n"
        . "  restrict access: true\n";
}

function module_settings_install_yml(): string
{
    return <<<'YAML'
templates:
  -
    name: interactive
    markup: "<!-- html:begin -->\n<!-- section.interactive-template -->\n{__markdown__}\n<!-- div.template-overlay --> <!-- strong --> <!-- Interactive template -->\n<!-- html:end -->"
  -
    name: rule
    markup: "<!-- html:begin -->\n<!-- section.rule-wrapper -->\n{__markdown__}\n<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Rule template -->\n<!-- html:end -->"

YAML;
}

function module_settings_schema_yml(string $machine, string $label): string
{
    return "{$machine}.settings:\n"
        . "  type: config_object\n"
        . "  label: " . yq($label . ' settings') . "\n"
        . "  mapping:\n"
        . "    templates:\n"
        . "      type: sequence\n"
        . "      label: 'Templates'\n"
        . "      sequence:\n"
        . "        type: mapping\n"
        . "        label: 'Template'\n"
        . "        mapping:\n"
        . "          name:\n"
        . "            type: string\n"
        . "            label: 'Name'\n"
        . "          markup:\n"
        . "            type: text\n"
        . "            label: 'Markup'\n";
}

function source_reference_note(string $kind): string
{
    $target = $kind === 'theme' ? 'Twig templates and the theme libraries' : 'the .module hooks and library definition';
    return "# WordPress source (reference)\n\n"
        . "These are the original Local CMS (WordPress-shaped) files this Drupal "
        . "{$kind} was ported from. They are kept for reference while porting "
        . "markup and logic into {$target}. This folder can be deleted once the "
        . "port is complete.\n";
}
