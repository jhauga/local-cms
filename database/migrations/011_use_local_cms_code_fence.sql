UPDATE pages
SET body_markdown = REPLACE(
    body_markdown,
    '```html
<!-- html:template=interactive -->
### Template Title
Wrapped markdown content
```',
    '```local-cms
<!-- html:template=interactive -->
### Template Title
Wrapped markdown content
```'
)
WHERE slug = 'markdown-conversion';