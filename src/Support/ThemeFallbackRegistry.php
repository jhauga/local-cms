<?php
declare(strict_types=1);

namespace Cms\Support;

/**
 * Decides what a synthesized theme-function fallback should return.
 *
 * A ported WordPress theme calls helpers the runtime has no real implementation
 * for (`newsx_get_option`, `hestia_get_setting`, ...). Rather than fataling, the
 * {@see ThemeFunctionBridge} defines a shim that asks this registry what a safe,
 * plausible return value is — "making a guess that this function is similar to a
 * built-in," as the porting brief puts it.
 *
 * The guess is driven by the function's NAME (an options getter should hand back
 * its caller-supplied default; an `is_*` predicate should be false; a `*_count`
 * should be 0; everything else gets the inert {@see LocalCmsSafeValue}). Every
 * rule is overridable, and the whole registry is persisted to
 * storage/theme-fallbacks.json so it can be edited from the admin pages.
 */
final class ThemeFallbackRegistry
{
    public const FILE = 'storage/theme-fallbacks.json';

    /** Return kinds a fallback can produce. */
    public const KINDS = ['option', 'bool_false', 'bool_true', 'zero', 'empty_string', 'empty_array', 'null', 'safe'];

    /**
     * Default name → kind rules, evaluated top to bottom; first match wins.
     * Kept deliberately conservative so a clean classic theme is barely touched.
     *
     * @var array<int, array{match: string, kind: string, note: string}>
     */
    private const DEFAULT_PATTERNS = [
        ['match' => '/(^|_)(get_)?(option|theme_mod|setting|mod)s?$/i', 'kind' => 'option',       'note' => 'options getter → return the caller default'],
        ['match' => '/_get_(option|setting|mod)\b/i',                    'kind' => 'option',       'note' => 'options getter → return the caller default'],
        ['match' => '/^(is|has|can|should|are)_/i',                      'kind' => 'bool_false',   'note' => 'predicate → false'],
        ['match' => '/(_enabled|_active|_visible|_is_active)$/i',        'kind' => 'bool_false',   'note' => 'predicate → false'],
        ['match' => '/(_count|_id|_width|_height|_size|_index|_total)$/i','kind' => 'zero',        'note' => 'numeric accessor → 0'],
        ['match' => '/(_list|_items|_ids|_array|_choices|_options|_args|_fields)$/i', 'kind' => 'empty_array', 'note' => 'collection accessor → []'],
        ['match' => '/^the_|_the_|(^|_)render(_|$)|^echo_|_echo$/i',     'kind' => 'empty_string', 'note' => 'echoing helper → output nothing'],
    ];

    /** @var array<string, mixed> exact function name → literal value or ['kind' => ...] */
    private array $overrides;

    /** @var array<int, array{match: string, kind: string, note: string}> */
    private array $patterns;

    private string $defaultKind;

    /**
     * @param array<string, mixed> $config Decoded theme-fallbacks.json (or [])
     */
    public function __construct(array $config = [])
    {
        $this->overrides = isset($config['overrides']) && is_array($config['overrides']) ? $config['overrides'] : [];
        $this->patterns = isset($config['patterns']) && is_array($config['patterns'])
            ? array_values(array_filter($config['patterns'], static fn ($p): bool => is_array($p) && isset($p['match'], $p['kind'])))
            : self::DEFAULT_PATTERNS;
        $this->defaultKind = isset($config['default_kind']) && in_array($config['default_kind'], self::KINDS, true)
            ? (string) $config['default_kind']
            : 'safe';
    }

    public static function load(string $rootPath): self
    {
        $path = rtrim($rootPath, '/\\') . '/' . self::FILE;
        $config = [];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }

        return new self($config);
    }

    public function save(string $rootPath): bool
    {
        $path = rtrim($rootPath, '/\\') . '/' . self::FILE;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $encoded = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded !== false && file_put_contents($path, $encoded . "\n") !== false;
    }

    /**
     * The fallback value for a function call, given its name and the arguments it
     * was invoked with (so an options getter can echo back the caller's default).
     */
    public function valueFor(string $name, array $args): mixed
    {
        $lower = strtolower($name);

        if (array_key_exists($lower, $this->overrides)) {
            $override = $this->overrides[$lower];

            if (is_array($override) && isset($override['kind'])) {
                return $this->valueForKind((string) $override['kind'], $args);
            }

            // A literal override value (string, number, bool, array) is returned as-is.
            return $override;
        }

        return $this->valueForKind($this->kindFor($name), $args);
    }

    /** The kind (see KINDS) the registry would use for a function name. */
    public function kindFor(string $name): string
    {
        $lower = strtolower($name);

        if (isset($this->overrides[$lower]) && is_array($this->overrides[$lower]) && isset($this->overrides[$lower]['kind'])) {
            $kind = (string) $this->overrides[$lower]['kind'];
            return in_array($kind, self::KINDS, true) ? $kind : $this->defaultKind;
        }

        foreach ($this->patterns as $rule) {
            if (@preg_match((string) $rule['match'], $name) === 1) {
                return (string) $rule['kind'];
            }
        }

        return $this->defaultKind;
    }

    private function valueForKind(string $kind, array $args): mixed
    {
        return match ($kind) {
            'option'       => $args[1] ?? '',           // mirror get_option($key, $default)
            'bool_false'   => false,
            'bool_true'    => true,
            'zero'         => 0,
            'empty_string' => '',
            'empty_array'  => [],
            'null'         => null,
            default        => LocalCmsSafeValue::instance(),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'overrides'    => $this->overrides,
            'patterns'     => $this->patterns,
            'default_kind' => $this->defaultKind,
        ];
    }

    /** @return array<string, mixed> */
    public function overrides(): array
    {
        return $this->overrides;
    }

    public function setOverride(string $name, mixed $value): void
    {
        $this->overrides[strtolower(trim($name))] = $value;
    }

    public function removeOverride(string $name): void
    {
        unset($this->overrides[strtolower(trim($name))]);
    }
}
