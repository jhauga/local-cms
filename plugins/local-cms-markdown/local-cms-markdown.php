<?php
/**
 * Plugin Name:       Local CMS Markdown
 * Plugin URI:        https://github.com/local-cms/local-cms
 * Description:       Client-side Markdown rendering for posts and pages, ported from the Local CMS theme. Toggle "Render as Markdown" on any post/page, or wrap a snippet in the [localcms_markdown] shortcode. Supports the marked.js renderer, GitHub-style alerts, tables, task lists, footnotes, HTML templates, and MathJax.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Local CMS
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       local-cms-markdown
 *
 * This plugin packages the Local CMS Markdown engine (public/assets/convert.js +
 * markdown.css) as a stand-alone WordPress plugin. The conversion itself runs in
 * the browser: the plugin emits a <div data-markdown-body> wrapper holding the raw
 * Markdown, mirrors the theme's body.localcms-theme hook, and enqueues the engine,
 * which renders every [data-markdown-body] element on the page.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Local_CMS_Markdown
{
    const VERSION       = '1.0.0';
    const META_ENABLED  = '_localcms_markdown_enabled';
    const META_RENDERER = '_localcms_markdown_renderer';
    const SHORTCODE     = 'localcms_markdown';
    const NONCE_ACTION  = 'localcms_markdown_save';
    const NONCE_FIELD   = 'localcms_markdown_nonce';

    const MENU_SLUG            = 'localcms-markdown';
    const OPTION_TEMPLATES     = 'localcms_markdown_templates';
    const SAVE_ACTION          = 'localcms_markdown_save_templates';
    const NONCE_TPL_ACTION     = 'localcms_markdown_templates';
    const NONCE_TPL_FIELD      = 'localcms_markdown_templates_nonce';

    /** @var bool Whether the current request will output Markdown. */
    private $needs_assets = false;

    /** @var bool Whether the current request contains math that needs MathJax. */
    private $needs_math = false;

    /** @var array<string,array{markdown:string,renderer:string}> Captured shortcode payloads. */
    private $shortcode_store = array();

    public static function bootstrap(): void
    {
        $plugin = new self();

        add_action('init', array($plugin, 'register'));
        add_action('add_meta_boxes', array($plugin, 'register_meta_box'));
        add_action('save_post', array($plugin, 'save_meta'), 10, 2);

        // Admin: sidebar menu + templating editor (the WordPress-compatible
        // counterpart of Local CMS's /admin/templating screen).
        add_action('admin_menu', array($plugin, 'register_admin_menu'));
        add_action('admin_post_' . self::SAVE_ACTION, array($plugin, 'handle_save_templates'));
        add_filter('localcms_markdown_templates', array($plugin, 'inject_saved_templates'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($plugin, 'settings_link'));

        // Decide early (before <head>) whether this view needs the engine.
        add_action('wp', array($plugin, 'detect_markdown'));
        add_action('wp_enqueue_scripts', array($plugin, 'enqueue_assets'));
        add_filter('body_class', array($plugin, 'filter_body_class'));

        // Whole-post Markdown (meta toggle): replace the rendered content with a wrapper.
        add_filter('the_content', array($plugin, 'render_post_content'), 99);

        // Shortcode: capture raw Markdown before wpautop mangles it, restore after.
        add_filter('the_content', array($plugin, 'capture_shortcodes'), 0);
        add_filter('the_content', array($plugin, 'restore_shortcodes'), 99);
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE, array($this, 'shortcode_passthrough'));

        // Expose the toggle/renderer meta to the REST API so the block editor can read them.
        $args = array(
            'show_in_rest'  => true,
            'single'        => true,
            'type'          => 'string',
            'auth_callback' => static function (): bool {
                return current_user_can('edit_posts');
            },
        );

        register_post_meta('', self::META_ENABLED, $args);
        register_post_meta('', self::META_RENDERER, $args);
    }

    /* ------------------------------------------------------------------ *
     *  Detection + assets
     * ------------------------------------------------------------------ */

    public function detect_markdown(): void
    {
        if (is_admin() || !is_singular()) {
            return;
        }

        $post = get_queried_object();

        if (!$post instanceof WP_Post) {
            return;
        }

        $enabled   = get_post_meta($post->ID, self::META_ENABLED, true) === '1';
        $has_block = has_shortcode((string) $post->post_content, self::SHORTCODE);

        if (!$enabled && !$has_block) {
            return;
        }

        $this->needs_assets = true;
        $this->needs_math   = $this->content_has_math((string) $post->post_content);
    }

    public function enqueue_assets(): void
    {
        if (!$this->needs_assets) {
            return;
        }

        $base = plugin_dir_url(__FILE__);

        wp_enqueue_style(
            'localcms-markdown',
            $base . 'assets/markdown.css',
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'localcms-markdown',
            $base . 'assets/convert.js',
            array(),
            self::VERSION,
            true
        );

        /**
         * Filter the HTML-wrapper templates available to the marked.js renderer.
         * Shape: [ 'template-name' => '<section>{__markdown__}</section>', ... ]
         *
         * @param array $templates
         */
        $templates = apply_filters('localcms_markdown_templates', array());

        wp_add_inline_script(
            'localcms-markdown',
            'window.LocalCmsMarkdownTemplates = ' . wp_json_encode((object) $templates) . ';',
            'before'
        );

        if ($this->needs_math) {
            $this->enqueue_mathjax();
        }
    }

    private function enqueue_mathjax(): void
    {
        wp_enqueue_script(
            'localcms-mathjax',
            'https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js',
            array(),
            null,
            true
        );

        // MathJax v3 reads window.MathJax for configuration before the script loads.
        $config = <<<'JS'
window.MathJax = {
    tex: {
        inlineMath: [['\\(', '\\)']],
        displayMath: [['\\[', '\\]']]
    },
    options: {
        skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code']
    }
};
JS;

        wp_add_inline_script('localcms-mathjax', $config, 'before');
    }

    public function filter_body_class(array $classes): array
    {
        // convert.js only auto-renders [data-markdown-body] when this class is present.
        if ($this->needs_assets && !in_array('localcms-theme', $classes, true)) {
            $classes[] = 'localcms-theme';
        }

        return $classes;
    }

    /* ------------------------------------------------------------------ *
     *  Whole-post Markdown (meta toggle)
     * ------------------------------------------------------------------ */

    public function render_post_content(string $content): string
    {
        if (is_admin() || !is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post = get_post();

        if (!$post instanceof WP_Post || get_post_meta($post->ID, self::META_ENABLED, true) !== '1') {
            return $content;
        }

        $renderer = get_post_meta($post->ID, self::META_RENDERER, true) === 'marked' ? 'marked' : 'default';

        // Use the raw, unfiltered Markdown rather than the wpautop'd $content.
        return self::wrap_markdown((string) $post->post_content, $renderer);
    }

    /* ------------------------------------------------------------------ *
     *  Shortcode
     * ------------------------------------------------------------------ */

    /**
     * Placeholder callback. The real work happens in capture_shortcodes()/
     * restore_shortcodes() so wpautop never sees the raw Markdown. This only runs
     * if those filters were bypassed (e.g. do_shortcode() called directly).
     */
    public function shortcode_passthrough($atts, ?string $content = null): string
    {
        $atts     = shortcode_atts(array('renderer' => 'default'), (array) $atts, self::SHORTCODE);
        $renderer = $atts['renderer'] === 'marked' ? 'marked' : 'default';

        return self::wrap_markdown((string) $content, $renderer);
    }

    public function capture_shortcodes(string $content): string
    {
        $this->shortcode_store = array();

        if (strpos($content, '[' . self::SHORTCODE) === false) {
            return $content;
        }

        $pattern = '/\[' . self::SHORTCODE . '\b([^\]]*)\]([\s\S]*?)\[\/' . self::SHORTCODE . '\]/';

        return (string) preg_replace_callback($pattern, function (array $m): string {
            $atts     = shortcode_parse_atts($m[1]);
            $atts     = is_array($atts) ? $atts : array();
            $renderer = (isset($atts['renderer']) && $atts['renderer'] === 'marked') ? 'marked' : 'default';

            // Trim a single wrapping newline so the editor's formatting is not echoed.
            $markdown = preg_replace('/^\R/', '', $m[2]);
            $markdown = preg_replace('/\R$/', '', (string) $markdown);

            $token = 'LOCALCMSMD' . count($this->shortcode_store) . 'ENDLOCALCMSMD';

            $this->shortcode_store[$token] = array(
                'markdown' => (string) $markdown,
                'renderer' => $renderer,
            );

            // Safety net: ensure assets load even if detect_markdown() missed this.
            $this->needs_assets = true;

            if ($this->content_has_math((string) $markdown)) {
                $this->needs_math = true;
            }

            return $token;
        }, $content);
    }

    public function restore_shortcodes(string $content): string
    {
        if (empty($this->shortcode_store)) {
            return $content;
        }

        foreach ($this->shortcode_store as $token => $data) {
            $wrapper = self::wrap_markdown($data['markdown'], $data['renderer']);

            // wpautop may have wrapped the bare token in <p>...</p>; unwrap that first.
            $content = str_replace(
                array('<p>' . $token . '</p>', $token),
                array($wrapper, $wrapper),
                $content
            );
        }

        return $content;
    }

    /* ------------------------------------------------------------------ *
     *  Editor UI
     * ------------------------------------------------------------------ */

    public function register_meta_box(): void
    {
        foreach (get_post_types(array('public' => true), 'names') as $post_type) {
            if ($post_type === 'attachment') {
                continue;
            }

            add_meta_box(
                'localcms-markdown',
                __('Local CMS Markdown', 'local-cms-markdown'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render_meta_box(WP_Post $post): void
    {
        $enabled  = get_post_meta($post->ID, self::META_ENABLED, true) === '1';
        $renderer = get_post_meta($post->ID, self::META_RENDERER, true) === 'marked' ? 'marked' : 'default';

        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        ?>
        <p>
            <label>
                <input type="checkbox" name="localcms_markdown_enabled" value="1" <?php checked($enabled); ?>>
                <?php esc_html_e('Render this content as Markdown', 'local-cms-markdown'); ?>
            </label>
        </p>
        <p>
            <label for="localcms_markdown_renderer"><strong><?php esc_html_e('Renderer', 'local-cms-markdown'); ?></strong></label><br>
            <select name="localcms_markdown_renderer" id="localcms_markdown_renderer" style="width:100%;">
                <option value="default" <?php selected($renderer, 'default'); ?>><?php esc_html_e('Built-in (Local CMS)', 'local-cms-markdown'); ?></option>
                <option value="marked" <?php selected($renderer, 'marked'); ?>><?php esc_html_e('marked.js (GFM)', 'local-cms-markdown'); ?></option>
            </select>
        </p>
        <p class="description">
            <?php esc_html_e('When enabled, the post body is treated as Markdown and rendered in the browser. Math ($...$, $$...$$, ```math) loads MathJax automatically.', 'local-cms-markdown'); ?>
        </p>
        <?php
    }

    public function save_meta(int $post_id, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce(sanitize_key($_POST[self::NONCE_FIELD]), self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // The meta box only renders on public, non-attachment types; mirror that here.
        if (!is_post_type_viewable($post->post_type)) {
            return;
        }

        if (!empty($_POST['localcms_markdown_enabled'])) {
            update_post_meta($post_id, self::META_ENABLED, '1');
        } else {
            delete_post_meta($post_id, self::META_ENABLED);
        }

        $renderer = (isset($_POST['localcms_markdown_renderer']) && $_POST['localcms_markdown_renderer'] === 'marked')
            ? 'marked'
            : 'default';

        update_post_meta($post_id, self::META_RENDERER, $renderer);
    }

    /* ------------------------------------------------------------------ *
     *  Admin menu + templating editor
     * ------------------------------------------------------------------ */

    public function register_admin_menu(): void
    {
        add_menu_page(
            __('Local CMS Markdown', 'local-cms-markdown'),
            __('Local CMS MD', 'local-cms-markdown'),
            'manage_options',
            self::MENU_SLUG,
            array($this, 'render_settings_page'),
            'dashicons-editor-code',
            58
        );
    }

    public function settings_link(array $links): array
    {
        $url = admin_url('admin.php?page=' . self::MENU_SLUG);

        array_unshift(
            $links,
            '<a href="' . esc_url($url) . '">' . esc_html__('Templates', 'local-cms-markdown') . '</a>'
        );

        return $links;
    }

    /**
     * Persist the markdown templates. Mirrors Local CMS's /admin/templating POST:
     * same name/markup pairing, same validation rules.
     */
    public function handle_save_templates(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage Markdown templates.', 'local-cms-markdown'));
        }

        check_admin_referer(self::NONCE_TPL_ACTION, self::NONCE_TPL_FIELD);

        $names   = isset($_POST['template_names']) && is_array($_POST['template_names']) ? (array) wp_unslash($_POST['template_names']) : array();
        $markups = isset($_POST['template_markups']) && is_array($_POST['template_markups']) ? (array) wp_unslash($_POST['template_markups']) : array();

        list($templates, $errors, $input) = $this->normalize_templates($names, $markups);

        if (!empty($errors)) {
            set_transient($this->notice_key(), array('errors' => $errors, 'input' => $input), MINUTE_IN_SECONDS);
            $this->redirect_to_settings(false);
            return;
        }

        update_option(self::OPTION_TEMPLATES, $templates);
        $this->redirect_to_settings(true);
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = get_transient($this->notice_key());

        if ($notice !== false) {
            delete_transient($this->notice_key());
        }

        $errors = is_array($notice) && !empty($notice['errors']) ? (array) $notice['errors'] : array();
        $rows   = is_array($notice) && !empty($notice['input']) ? (array) $notice['input'] : $this->get_templates();

        // Always show a trailing blank row so authors can add another template.
        $rows[] = array('name' => '', 'markup' => '');

        $saved = isset($_GET['updated']) && $_GET['updated'] === '1';
        ?>
        <div class="wrap localcms-md-templating">
            <h1><?php esc_html_e('Local CMS Markdown — Templating', 'local-cms-markdown'); ?></h1>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: the {__markdown__} placeholder token. */
                    esc_html__('Create reusable HTML wrapper snippets for Markdown conversion. Use %s where the authored Markdown should be inserted.', 'local-cms-markdown'),
                    '<code>{__markdown__}</code>'
                );
                ?>
            </p>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Templates saved.', 'local-cms-markdown'); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error">
                    <?php foreach ($errors as $error) : ?>
                        <p><?php echo esc_html((string) $error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:none;">
                <h2 class="title"><?php esc_html_e('Usage', 'local-cms-markdown'); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: 1: html:template comment, 2: html:begin comment. */
                        esc_html__('Save a template here, then call it in Markdown with %1$s. Snippets can also use wrapper comments like %2$s and sibling element comments.', 'local-cms-markdown'),
                        '<code>&lt;!-- html:template=template-name --&gt;</code>',
                        '<code>&lt;!-- html:begin --&gt;</code>'
                    );
                    ?>
                </p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-template-form>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <?php wp_nonce_field(self::NONCE_TPL_ACTION, self::NONCE_TPL_FIELD); ?>

                <div data-template-list>
                    <?php foreach ($rows as $row) : ?>
                        <?php $this->render_template_row((string) ($row['name'] ?? ''), (string) ($row['markup'] ?? '')); ?>
                    <?php endforeach; ?>
                </div>

                <template id="localcms-md-row-template">
                    <?php $this->render_template_row('', ''); ?>
                </template>

                <p class="submit">
                    <button type="button" class="button" data-add-template><?php esc_html_e('Add template', 'local-cms-markdown'); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save templates', 'local-cms-markdown'); ?></button>
                </p>
            </form>
        </div>

        <style>
            .localcms-md-templating .localcms-md-card { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:12px 16px; margin:16px 0; }
            .localcms-md-templating .localcms-md-card-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
            .localcms-md-templating .localcms-md-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:8px 0; }
            .localcms-md-templating label > span { display:block; font-weight:600; margin-bottom:4px; }
            .localcms-md-templating input[type=text], .localcms-md-templating textarea { width:100%; }
            .localcms-md-templating textarea.code { font-family:Consolas,Monaco,monospace; }
            @media (max-width:782px){ .localcms-md-templating .localcms-md-grid { grid-template-columns:1fr; } }
        </style>

        <script>
        (function () {
            var form = document.querySelector('[data-template-form]');
            if (!form) { return; }
            var list = form.querySelector('[data-template-list]');
            var rowTemplate = document.getElementById('localcms-md-row-template');

            form.addEventListener('input', function (event) {
                var target = event.target;
                if (!target || target.name !== 'template_names[]') { return; }
                var row = target.closest('[data-template-row]');
                var call = row && row.querySelector('[data-author-call]');
                if (call) { call.value = '<!-- html:template=' + (target.value || 'template-name') + ' -->'; }
            });

            form.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) { return; }

                if (target.matches('[data-add-template]')) {
                    event.preventDefault();
                    if (list && rowTemplate) { list.appendChild(rowTemplate.content.cloneNode(true)); }
                    return;
                }

                if (!target.matches('[data-remove-template]')) { return; }
                event.preventDefault();
                var row = target.closest('[data-template-row]');
                if (!row) { return; }

                if (list && list.querySelectorAll('[data-template-row]').length <= 1) {
                    var name = row.querySelector('input[name="template_names[]"]');
                    var markup = row.querySelector('textarea[name="template_markups[]"]');
                    if (name) { name.value = ''; }
                    if (markup) { markup.value = ''; }
                    return;
                }
                row.remove();
            });
        })();
        </script>
        <?php
    }

    private function render_template_row(string $name, string $markup): void
    {
        $call = '<!-- html:template=' . ($name !== '' ? $name : 'template-name') . ' -->';
        ?>
        <div class="localcms-md-card" data-template-row>
            <div class="localcms-md-card-head">
                <div>
                    <h2 class="title" style="margin-top:0;"><?php esc_html_e('Template snippet', 'local-cms-markdown'); ?></h2>
                    <p class="description"><?php esc_html_e('Names stay short and stable — authors reference them directly in Markdown comments.', 'local-cms-markdown'); ?></p>
                </div>
                <button type="button" class="button-link delete" data-remove-template><?php esc_html_e('Remove', 'local-cms-markdown'); ?></button>
            </div>

            <div class="localcms-md-grid">
                <label>
                    <span><?php esc_html_e('Template name', 'local-cms-markdown'); ?></span>
                    <input type="text" name="template_names[]" value="<?php echo esc_attr($name); ?>" placeholder="callout">
                </label>
                <label>
                    <span><?php esc_html_e('Author call', 'local-cms-markdown'); ?></span>
                    <input type="text" data-author-call value="<?php echo esc_attr($call); ?>" readonly>
                </label>
            </div>

            <label>
                <span><?php esc_html_e('HTML snippet', 'local-cms-markdown'); ?></span>
                <textarea class="code" name="template_markups[]" rows="8" placeholder="&lt;!-- html:begin --&gt;&#10;&lt;!-- section.example-template --&gt;&#10;{__markdown__}&#10;&lt;!-- html:end --&gt;"><?php echo esc_textarea($markup); ?></textarea>
            </label>
        </div>
        <?php
    }

    /**
     * @param array $names
     * @param array $markups
     * @return array{0:array<int,array{name:string,markup:string}>,1:string[],2:array<int,array{name:string,markup:string}>}
     */
    private function normalize_templates(array $names, array $markups): array
    {
        $templates = array();
        $errors    = array();
        $input     = array();
        $seen      = array();
        $count     = max(count($names), count($markups));

        for ($i = 0; $i < $count; $i++) {
            $name   = trim((string) ($names[$i] ?? ''));
            $markup = trim((string) ($markups[$i] ?? ''));
            $label  = sprintf(
                /* translators: %d: row number. */
                __('Template %d', 'local-cms-markdown'),
                $i + 1
            );

            if ($name === '' && $markup === '') {
                continue;
            }

            $input[] = array('name' => $name, 'markup' => $markup);

            $valid = true;

            if ($name === '') {
                $errors[] = sprintf(__('%s is missing a name.', 'local-cms-markdown'), $label);
                $valid = false;
            }

            if ($markup === '') {
                $errors[] = sprintf(__('%s is missing its HTML snippet.', 'local-cms-markdown'), $label);
                $valid = false;
            }

            if ($name !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
                $errors[] = sprintf(__('%s must use only letters, numbers, underscores, or hyphens.', 'local-cms-markdown'), $label);
                $valid = false;
            }

            if ($name !== '' && isset($seen[$name])) {
                $errors[] = sprintf(__('%1$s duplicates the template name "%2$s".', 'local-cms-markdown'), $label, $name);
                $valid = false;
            }

            if ($markup !== '' && strpos($markup, '{__markdown__}') === false) {
                $errors[] = sprintf(__('%s must include the {__markdown__} placeholder.', 'local-cms-markdown'), $label);
                $valid = false;
            }

            if ($valid) {
                $seen[$name] = true;
                $templates[] = array('name' => $name, 'markup' => $markup);
            }
        }

        return array($templates, $errors, $input);
    }

    /** @return array<int,array{name:string,markup:string}> */
    private function get_templates(): array
    {
        $stored = get_option(self::OPTION_TEMPLATES, false);

        if ($stored === false) {
            return self::default_templates();
        }

        if (!is_array($stored)) {
            return array();
        }

        $templates = array();

        foreach ($stored as $template) {
            if (!is_array($template)) {
                continue;
            }

            $name   = trim((string) ($template['name'] ?? ''));
            $markup = trim((string) ($template['markup'] ?? ''));

            if ($name !== '' && $markup !== '') {
                $templates[] = array('name' => $name, 'markup' => $markup);
            }
        }

        return $templates;
    }

    /** @return array<string,string> name => markup */
    public function inject_saved_templates(array $templates): array
    {
        foreach ($this->get_templates() as $template) {
            $templates[$template['name']] = $template['markup'];
        }

        return $templates;
    }

    /** @return array<int,array{name:string,markup:string}> */
    private static function default_templates(): array
    {
        return array(
            array(
                'name'   => 'interactive',
                'markup' => "<!-- html:begin -->\n<!-- section.interactive-template -->\n{__markdown__}\n<!-- div.template-overlay --> <!-- strong --> <!-- Interactive template -->\n<!-- html:end -->",
            ),
            array(
                'name'   => 'rule',
                'markup' => "<!-- html:begin -->\n<!-- section.rule-wrapper -->\n{__markdown__}\n<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Rule template -->\n<!-- html:end -->",
            ),
        );
    }

    private function notice_key(): string
    {
        return 'localcms_md_notice_' . get_current_user_id();
    }

    private function redirect_to_settings(bool $updated): void
    {
        $url = admin_url('admin.php?page=' . self::MENU_SLUG);

        if ($updated) {
            $url = add_query_arg('updated', '1', $url);
        }

        wp_safe_redirect($url);
        exit;
    }

    /* ------------------------------------------------------------------ *
     *  Helpers
     * ------------------------------------------------------------------ */

    /**
     * Wrap raw Markdown for client-side rendering. esc_html() keeps the source
     * literal; convert.js reads it back via element.textContent.
     */
    private static function wrap_markdown(string $markdown, string $renderer): string
    {
        $attr = $renderer === 'marked' ? ' data-markdown-renderer="marked"' : '';

        return '<div data-markdown-body' . $attr . '>' . "\n"
            . esc_html($markdown) . "\n"
            . '</div>';
    }

    private function content_has_math(string $content): bool
    {
        // Display math fences / $$...$$, inline $`...`$, or a bare $...$ span.
        return (bool) preg_match('/```\s*math|\$\$|\$`[^`]+`\$|(?<!\\\\)\$[^$\n]+\$/', $content);
    }
}

Local_CMS_Markdown::bootstrap();
