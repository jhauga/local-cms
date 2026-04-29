UPDATE pages
SET excerpt = 'A compact publishing system with richer content models, taxonomy support, and a safer Markdown pipeline.',
    body_markdown = '# Publish without the framework weight\n\nLocal CMS now pairs a small PHP core with a fuller content model. Write in Markdown, store structure in SQLite, and keep the theme layer portable.\n\n## What changed in this slice\n\n1. Posts and pages now carry author and meta fields.\n2. Categories and tags can drive archive views.\n3. Markdown supports quotes, code fences, lists, links, and inline formatting.\n\n> The content model stays separate from the theme layer so the same templates can later move into WordPress.\n\nExplore the [post archive](/posts) or read the [workflow page](/page/editorial-workflow).',
    meta_description = 'Local CMS now supports richer content metadata, taxonomy archives, and safer Markdown rendering.',
    sort_order = 0
WHERE slug = 'home';

UPDATE pages
SET body_markdown = '# About Local CMS\n\nThis scaffold is designed for fast iteration while keeping the presentation layer portable.\n\n- Content lives in SQLite.\n- Rendering happens through a safe Markdown pipeline.\n- Templates stay close to WordPress naming conventions.\n\n> A CMS should let content structure evolve without forcing the theme to change shape.',
    meta_description = 'Why Local CMS keeps the backend small and the theme layer portable.',
    sort_order = 10
WHERE slug = 'about';

INSERT OR IGNORE INTO pages (
    slug,
    title,
    excerpt,
    body_markdown,
    status,
    published_at,
    author_id,
    template,
    meta_title,
    meta_description,
    sort_order
) VALUES (
    'editorial-workflow',
    'Editorial Workflow',
    'How content, taxonomy, and theme rendering stay decoupled.',
    '## Editorial workflow\n\n1. Draft pages and posts in Markdown.\n2. Attach categories or tags for archive organization.\n3. Render through theme templates that can later map to WordPress.\n\n```php\n<?php\n// The frontend reads structured content, not hardcoded page markup.\n$route = ''/post/{slug}'';\n```\n\nUse `archive.php` for collections and `single.php` for individual entries.',
    'published',
    CURRENT_TIMESTAMP,
    (SELECT id FROM users ORDER BY id ASC LIMIT 1),
    'page',
    'Editorial Workflow',
    'How Local CMS keeps the content model separate from the theme layer.',
    20
);

UPDATE posts
SET body_markdown = '# First launch note\n\nThe first scaffold was intentionally small. This next slice makes the content layer more realistic.\n\n## Included now\n\n- richer metadata fields\n- category and tag relationships\n- safer Markdown rendering\n\n```php\n<?php\n$app = require ''bootstrap/app.php'';\n```\n\nRead the [theme portability note](/post/theme-portability) next.',
    meta_description = 'A summary of the richer content-model and Markdown improvements.'
WHERE slug = 'first-launch-note';

UPDATE posts
SET body_markdown = '# Theme portability\n\nUsing `header.php`, `footer.php`, `index.php`, `page.php`, `single.php`, and `archive.php` keeps the view layer close to WordPress.\n\n> The backend can evolve independently as long as the theme receives clean data.\n\nThat separation is what makes later extraction into a WordPress theme practical.',
    meta_description = 'Why Local CMS uses WordPress-shaped template boundaries.'
WHERE slug = 'theme-portability';

INSERT OR IGNORE INTO terms (taxonomy, slug, name, description) VALUES
    ('category', 'writing-workflow', 'Writing Workflow', 'Editorial structure and authoring flow.'),
    ('tag', 'content-model', 'Content Model', 'Metadata, terms, and structured publishing.');

INSERT OR IGNORE INTO posts (
    slug,
    title,
    excerpt,
    body_markdown,
    status,
    published_at,
    author_id,
    meta_title,
    meta_description
) VALUES (
    'writing-with-markdown',
    'Writing with Markdown',
    'How the CMS now handles safer Markdown rendering for authors.',
    '# Writing with Markdown\n\nMarkdown now supports:\n\n- headings\n- ordered and unordered lists\n- blockquotes\n- fenced code blocks\n- inline `code` and [links](/posts)\n\n## Safe output matters\n\nThe fallback renderer escapes raw HTML, allows only safe links, and keeps content portable between systems.',
    'published',
    datetime(CURRENT_TIMESTAMP, '-2 day'),
    (SELECT id FROM users ORDER BY id ASC LIMIT 1),
    'Writing with Markdown',
    'A walkthrough of the safer Markdown pipeline used by Local CMS.'
);

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 0
FROM posts p
JOIN terms t ON t.taxonomy = 'category' AND t.slug = 'writing-workflow'
WHERE p.slug = 'writing-with-markdown';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 1
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'markdown'
WHERE p.slug = 'writing-with-markdown';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 2
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'content-model'
WHERE p.slug = 'writing-with-markdown';
