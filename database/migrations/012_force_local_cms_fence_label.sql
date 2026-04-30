UPDATE pages
SET body_markdown = REPLACE(body_markdown, '```html', '```local-cms')
WHERE slug = 'markdown-conversion';