UPDATE users
SET password_hash = '$2y$10$kRV15q/TH3ZIK18EOHm3ie2q7gwJF9xFmdu2J2Jhuk9DvICT/CaZ6',
    display_name = 'Admin'
WHERE email = 'admin@example.com';

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
    'contact',
    'Contact',
    'A draft contact page for the admin workflow.',
    '## Contact\n\nUse this draft page to test the page editor, slug management, and publishing workflow from the admin area.',
    'draft',
    NULL,
    (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
    'page',
    'Contact',
    'A draft contact page for the Local CMS admin workflow.',
    30
);

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
    'launch-checklist',
    'Launch Checklist',
    'A draft checklist post to test the admin editor.',
    '# Launch checklist\n\n- Finalize navigation\n- Review taxonomy assignments\n- Publish the latest content update',
    'draft',
    NULL,
    (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
    'Launch Checklist',
    'A draft checklist post used for the Local CMS admin workflow.'
);
