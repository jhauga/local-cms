# Local CMS TODO

## Current

- [x] Convert Markdown images
- [x] Create `awesome-copilot` tools
  - [x] New skill `content-management-systems`
  - [x] New plugin `content-management-system-development`
- [x] Render the default theme inside a stock WordPress install
  - [x] Normalize taxonomy terms so `WP_Term` objects read like local-cms term arrays
  - [x] Limit excerpts to authored post excerpts; omit them on pages
  - [x] Resolve post, page, and term URLs through runtime-aware helpers
- [x] Add a `plugins/` workspace and the `Local CMS Markdown` WordPress plugin
  - [x] Admin `Templating` screen and `[localcms_markdown]` shortcode
- [x] Add cross-platform `export.bat` and `export.sh` packaging scripts
- [ ] Expand the built-in markdown template catalog from 2 defaults to 4 seeded examples

## Major

- [ ] Add a built-in `defaultFormat` option that initializes new pages and posts with the correct editor state, save defaults, and conversion path
- [ ] Centralize new-content defaults so the admin create flow, seeded content, and static export all resolve format behavior the same way
- [ ] Add four first-party template presets beyond `interactive` and `rule`, aimed at callouts, comparisons, hero sections, and documentation notes
- [ ] Add `config.json` validation with surfaced fallback notices for invalid `theme.name`, `theme.media`, and `application.uploads.datePath` values
- [ ] Add regression coverage for title markdown, wrapper templates, and client-converter parity between live pages and static export

## Minor

- [ ] Show the resolved `defaultFormat` and client-converter state in the content editor
- [ ] Add a short templating cheat sheet for `<!-- html:template=name -->` and `{__markdown__}`
- [ ] Add an empty-state message when no custom templates have been saved yet
- [ ] Allow template rows to be reordered before saving
- [ ] Add admin list badges for `marked.js`, Markdown Math, and template-enabled content
- [ ] Keep slug auto-generation active only until the slug is manually edited
- [ ] Seed one demo page and one demo post that exercise every built-in template preset

## Patch

- [ ] Normalize `defaultFormat` casing before config merge
- [ ] Ignore unsupported `defaultFormat` values and fall back cleanly to `markdown`
- [ ] Prevent duplicate template names with a field-level validation error
- [ ] Trim template names and template markup before persistence
- [ ] Preserve template row order after validation errors
- [ ] Include the failing row number in templating validation messages
- [ ] Avoid double slashes in upload paths when `datePath` is empty
- [ ] Surface invalid `theme.media` names with a clearer fallback note
- [ ] Surface missing theme directories with a clearer fallback note
- [ ] Ensure title-markdown code tokens wrap cleanly in narrow nav and footer layouts
- [ ] Treat whitespace-only title markdown output as empty before rendering
- [ ] Keep `<title>` output plain text even when nested inline title tags are present
- [ ] Add a focused smoke check for config and templating changes to the validation section
- [ ] Clean up typos and wording drift in docs, TODO items, and admin hints as features land
