# Local CMS adapter (inbound)

Ports a stock **WordPress theme** into a **Local CMS-compatible** theme. This is
the only *inbound* adapter: every other target (drupal, joomla, ...) takes a
Local CMS theme and reshapes it for a foreign platform, while `local-cms`
reverses that direction and pulls a WordPress theme into this repo.

Invoked automatically by `port-cms` when the target CMS is `local-cms`; the OS
entry points (`transform.sh`, `transform.bat`) delegate to `transform.php`, so
this adapter requires PHP on the `PATH`.

```bash
# Windows
port-cms local-cms themes/twentytwentyone

# Linux/macOS
./port-cms.sh local-cms themes/twentytwentyone
```

## How it works

The Local CMS runtime decides theme compatibility **statically**, from a marker
in the theme's `style.css` header (`Local CMS Runtime: compatible`), never by
executing the theme — a foreign `functions.php` may call `exit()`/`die()` when
WordPress is absent. See [src/Support/ThemeCatalog.php](../../src/Support/ThemeCatalog.php).

`port-cms` stages a clean copy of `themes/<name>` under
`_port-themes/local-cms/<name>/` (gitignored), then this adapter rewrites that
copy in place:

1. **Validates** the source is a portable WordPress theme. It must have a
   `style.css` carrying a `Theme Name` header and an `index.php` base template.
2. **Checks the license** and refuses the port unless the theme's license
   permits modifying and redistributing the source (see
   [License gate](#license-gate)).
3. **Stamps** `Local CMS Runtime: compatible` into the `style.css` header so the
   runtime offers the theme for activation (idempotent — re-running is safe).
4. **Neutralizes** the canonical `defined( 'ABSPATH' ) || exit;` guards across
   the theme's PHP files, commenting them out with a note, so including the
   theme can never terminate the Local CMS process.
5. **Writes** `LICENSE_NOTE.txt` into the ported theme, recording the detected
   license and stating that the port does not relicense the theme (see
   [LICENSE_NOTE.txt](#license_notetxt)).
6. **Screens the templates** and substitutes minimal portable equivalents for
   any that depend on a page-builder runtime the Local CMS runtime cannot drive
   (see [Template portability screen](#template-portability-screen)).

The ported theme runs against a broad WordPress compatibility layer
([src/Support/WordPressCompat.php](../../src/Support/WordPressCompat.php)), so a
stock theme that sticks to common template tags, conditional tags, theme-setup
hooks, and enqueue calls renders without manual edits. The runtime also loads
the theme's `functions.php` defensively: a single unshimmed helper degrades that
one call rather than taking down the whole request.

If the source cannot be ported, the adapter raises a `PortCompatibilityException`
and exits non-zero with the reason — for example:

```
[local-cms] Cannot port "broken-theme": the source has no style.css, so it is not a WordPress theme.
```

## License gate

A port alters the source, so the adapter only proceeds when the theme's license
permits modifying and redistributing it. The license is detected, in order,
from the `style.css` `License` header, `readme.txt`, `package.json`, then a
bundled `LICENSE`/`COPYING` file.

Two allowlists in [transform.php](transform.php) drive the decision:

- **`PERMISSIVE_LICENSE_RULES`** — licenses that allow altering the source, such
  as the GPL family, MIT, Apache-2.0, BSD, MPL-2.0, ISC, zlib, WTFPL, public
  domain / Unlicense, CC0, and Creative Commons variants that permit
  derivatives (`CC BY`, `CC BY-SA`, `CC BY-NC`).
- **`FORBIDDEN_LICENSE_RULES`** — licenses that forbid derivatives or are
  proprietary (the `NoDerivatives` family, `all rights reserved`). These are
  checked first and always win, so a theme that is otherwise Creative Commons
  but carries `ND` is still refused.

A theme whose license cannot be determined is treated as not portable. In every
refusal the adapter exits non-zero with the reason:

```
[local-cms] Cannot port "premium-theme": its license ("All Rights Reserved") is not portable: it is proprietary / all-rights-reserved, so the source may not be altered or redistributed.
```

To allow an additional license, add a matching pattern to
`PERMISSIVE_LICENSE_RULES`; to block one, add it to `FORBIDDEN_LICENSE_RULES`.

## LICENSE_NOTE.txt

Every successful port writes `LICENSE_NOTE.txt` into the theme. It records the
theme name, the detected license and its source, and the port date, then states
plainly that the port adapts the theme to the runtime but does **not** relicense
it — the original license still governs and must not be changed. When the theme
ships its own `LICENSE`/`COPYING` file, that file is preserved unchanged and the
note points to it as the authoritative statement of the license.

## Template portability screen

The compatibility layer covers the **classic** WordPress theming surface — the
loop, template tags, conditional tags, and `get_template_part()` delegation. It
cannot drive a **page-builder runtime**: a component framework that renders a
page through `$obj->get( 'header' )->render()`, a theme singleton whose body is
emitted entirely by `do_action()` hooks, or block templates. Left intact, those
templates render blank or fatal under the runtime.

The classic [Twenty Twenty-One](https://wordpress.org/themes/twentytwentyone/)
theme ports cleanly precisely because it stays on the classic surface. The
screen takes that as its model: a **small set of WordPress elements that
actually render beats the whole theme rendering sub-par.** So the adapter
inspects each renderable template and, for every one that depends on an
unsupported runtime, swaps in the matching file from
[`templates/`](templates/) — a proven, runtime-safe set modelled on Twenty
Twenty-One that uses only supported tags and standard class names, so the
theme's own `style.css` still applies.

For each of `index`, `single`, `page`, `archive`, `404`, `header`, `footer` (and
`front-page`, `home`, `search` when present), the screen does one of:

| Verdict       | When                                                        | Action                                                            |
| :--           | :--                                                         | :--                                                               |
| **Kept**      | The template already uses the supported surface             | Left untouched                                                    |
| **Replaced**  | The template depends on a builder runtime or hook-only body | Original moved to `_unported/`; minimal portable template written |
| **Generated** | An `index`/`single`/`page`/`archive`/`404` route is missing | Minimal portable template written                                 |

`header.php` and `footer.php` are decided as a pair, so the opening and closing
markup always match. Replaced originals are preserved unchanged under
`_unported/` and can be restored by moving them back — though they will only
render if their builder runtime is also made available to the runtime. The pass
is idempotent: minimal templates carry a marker and are never re-screened, and
thin stubs from earlier porter versions are upgraded to the current set.

Detection is deliberately conservative — a template is only replaced on a strong
builder signal, so a clean classic theme is never rewritten.

### PORT_REPORT.txt

Every port writes `PORT_REPORT.txt` into the theme: the screen's verdict for
each template (kept / replaced / generated) and the builder runtimes detected,
so the operator can see exactly how much of the theme survived the port and why
the rest was substituted.

## Overwrite prompt

On success, `port-cms` asks before writing back under `themes/`:

```
overwrite - y or n?
```

| Answer | Destination          | Effect                                            |
| :--    | :--                  | :--                                               |
| `y`    | `themes/<name>`      | Overwrites the original theme in place            |
| `n`    | `themes/port-<name>` | Writes a converted copy, leaving the original     |

Anything other than `y` is treated as `n`, so the original theme is never
replaced without explicit confirmation.

## Theme function bridge

Porting handles a theme's files and license; the **theme function bridge**
handles what those files *call* at render time. A stock theme leans on a forest
of helpers — its own (`acme_get_option`), bundled frameworks, and plugins it
assumes are active — most of which the runtime has never heard of. The first
undefined call would fatal, and because that often happens inside `functions.php`
while it pulls a framework, every helper defined after it — including the theme's
own — silently never loads.

The bridge ([src/Support/ThemeFunctionBridge.php](../../src/Support/ThemeFunctionBridge.php))
breaks that cascade when a ported theme renders:

1. **Before `functions.php`** it statically finds every function the theme's
   source calls but defines nowhere (the external dependencies) and defines a
   safe shim for each, so `functions.php` runs to completion and the theme's own
   real helpers load.
2. **After `functions.php`** it shims any function the templates still call that
   is undefined — a last safety net before render.
3. A **fallback class autoloader** does the same for classes the theme
   instantiates but never defines (a custom nav walker, a widget), defining each
   as a benign stub.

Each shim returns a plausible, inert value chosen by name: an options getter
hands back its caller's default, an `is_*` predicate returns false, a `*_count`
returns 0, and anything else returns a value that is safe to echo, iterate,
index, count, or chain a method on. Those guesses live in an editable registry
([src/Support/ThemeFallbackRegistry.php](../../src/Support/ThemeFallbackRegistry.php),
persisted to `storage/theme-fallbacks.json`) and can be overridden per function
from the admin **Theme Bridge** screen. Missing WordPress-core functions are
filled by the broader compatibility layer with type-correct implementations, so
a template can pass their results straight into `join()`/`array_map()`/`foreach`.

The net effect, and the porting goal: a ported theme renders with its **real
templates and real styling**, recovers as many of its **real functions** as
possible, and falls back safely on the rest — instead of fataling on the first
helper the runtime lacks.

## Limits

The compatibility layer plus the [function bridge](#theme-function-bridge) keep
a ported theme from fataling, but they cannot supply data the runtime does not
have. A theme that depends on the block editor, widgets backed by a database,
comment threads, customizer-driven options, or other WordPress-only subsystems
renders those parts as empty or inert rather than failing — its option getters
return defaults, its widget areas are blank, its dynamic sections collapse.
Simpler classic themes port seamlessly; the more a theme leans on WordPress
internals, the more of it degrades gracefully instead of rendering.

Templates that depend on a page-builder runtime (a component framework, a
hook-only body, block templates) are a harder case: they render blank or fatal,
not merely degraded. For those the [template portability
screen](#template-portability-screen) substitutes a minimal portable template so
the route still shows real content with the theme's own styling, trading the
theme's bespoke layout for one that works. The original is preserved under
`_unported/` for anyone who later wires the builder runtime into the runtime.

Compare the ported templates against the [default theme](../../themes/default),
which models the runtime-compatible patterns, when filling any remaining gaps.
