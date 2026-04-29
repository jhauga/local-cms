UPDATE settings
SET value = 'Local CMS'
WHERE key = 'site_name';

UPDATE pages
SET title = CASE WHEN slug = 'home' THEN 'Local CMS' ELSE title END,
    body_markdown = REPLACE(body_markdown, 'Harbor CMS', 'Local CMS'),
    meta_title = REPLACE(COALESCE(meta_title, title), 'Harbor CMS', 'Local CMS'),
    meta_description = REPLACE(COALESCE(meta_description, ''), 'Harbor CMS', 'Local CMS')
WHERE slug IN ('home', 'about', 'editorial-workflow');

UPDATE posts
SET body_markdown = REPLACE(body_markdown, 'Harbor CMS', 'Local CMS'),
    meta_title = REPLACE(COALESCE(meta_title, title), 'Harbor CMS', 'Local CMS'),
    meta_description = REPLACE(COALESCE(meta_description, ''), 'Harbor CMS', 'Local CMS');
