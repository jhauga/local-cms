INSERT INTO settings (key, value)
VALUES (
    'markdown_templates',
    '[
  {
    "name": "interactive",
    "markup": "<!-- html:begin -->\n<!-- section.interactive-template -->\n{__markdown__}\n<!-- div.template-overlay --> <!-- strong --> <!-- Interactive template -->\n<!-- html:end -->"
  },
  {
    "name": "rule",
    "markup": "<!-- html:begin -->\n<!-- section.rule-wrapper -->\n{__markdown__}\n<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Rule template -->\n<!-- html:end -->"
  }
]'
)
ON CONFLICT(key) DO NOTHING;