ALTER TABLE pages ADD COLUMN author_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE pages ADD COLUMN template TEXT NOT NULL DEFAULT 'page';
ALTER TABLE pages ADD COLUMN meta_title TEXT;
ALTER TABLE pages ADD COLUMN meta_description TEXT;
ALTER TABLE pages ADD COLUMN featured_image TEXT;
ALTER TABLE pages ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0;

ALTER TABLE posts ADD COLUMN author_id INTEGER REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE posts ADD COLUMN meta_title TEXT;
ALTER TABLE posts ADD COLUMN meta_description TEXT;
ALTER TABLE posts ADD COLUMN featured_image TEXT;

CREATE TABLE IF NOT EXISTS terms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    taxonomy TEXT NOT NULL,
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    UNIQUE (taxonomy, slug)
);

CREATE TABLE IF NOT EXISTS content_terms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content_type TEXT NOT NULL,
    content_id INTEGER NOT NULL,
    term_id INTEGER NOT NULL REFERENCES terms(id) ON DELETE CASCADE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE (content_type, content_id, term_id)
);

CREATE INDEX IF NOT EXISTS idx_content_terms_lookup ON content_terms (content_type, content_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_terms_taxonomy_slug ON terms (taxonomy, slug);

UPDATE pages
SET author_id = COALESCE(author_id, (SELECT id FROM users ORDER BY id ASC LIMIT 1)),
    template = COALESCE(NULLIF(template, ''), 'page'),
    meta_title = COALESCE(meta_title, title),
    meta_description = COALESCE(meta_description, excerpt),
    sort_order = CASE WHEN slug = 'home' THEN 0 ELSE sort_order END;

UPDATE posts
SET author_id = COALESCE(author_id, (SELECT id FROM users ORDER BY id ASC LIMIT 1)),
    meta_title = COALESCE(meta_title, title),
    meta_description = COALESCE(meta_description, excerpt);

INSERT OR IGNORE INTO terms (taxonomy, slug, name, description) VALUES
    ('category', 'announcements', 'Announcements', 'Launch updates and release notes.'),
    ('category', 'theme-system', 'Theme System', 'Notes about theming and WordPress portability.'),
    ('tag', 'starter', 'Starter', 'Early-stage project notes.'),
    ('tag', 'wordpress', 'WordPress', 'Theme portability and compatibility notes.'),
    ('tag', 'markdown', 'Markdown', 'Writing and rendering workflow notes.');

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 0
FROM posts p
JOIN terms t ON t.taxonomy = 'category' AND t.slug = 'announcements'
WHERE p.slug = 'first-launch-note';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 0
FROM posts p
JOIN terms t ON t.taxonomy = 'category' AND t.slug = 'theme-system'
WHERE p.slug = 'theme-portability';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 1
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'starter'
WHERE p.slug = 'first-launch-note';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'post', p.id, t.id, 1
FROM posts p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'wordpress'
WHERE p.slug = 'theme-portability';

INSERT OR IGNORE INTO content_terms (content_type, content_id, term_id, sort_order)
SELECT 'page', p.id, t.id, 0
FROM pages p
JOIN terms t ON t.taxonomy = 'tag' AND t.slug = 'markdown'
WHERE p.slug = 'about';
