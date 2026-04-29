UPDATE pages
SET meta_title = REPLACE(COALESCE(meta_title, title), 'Harbor CMS', 'Local CMS');

UPDATE posts
SET meta_title = REPLACE(COALESCE(meta_title, title), 'Harbor CMS', 'Local CMS');
