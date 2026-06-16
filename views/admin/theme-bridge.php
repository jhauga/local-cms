<?php
declare(strict_types=1);

/**
 * Theme Bridge admin screen.
 *
 * Surfaces the theme-specific functions ("elements") the active ported theme
 * calls that the runtime has no real implementation for, and the fallback the
 * bridge gives each. Every fallback is editable here and persisted to
 * storage/theme-fallbacks.json, so a guess the runtime made about a theme
 * function can be corrected without touching code.
 */

$detected = $detected ?? [];
$overrides = $overrides ?? [];
$kinds = $kinds ?? [];

$kindLabels = [
    ''             => 'Smart default',
    'option'       => 'Option getter (return caller default)',
    'bool_false'   => 'Boolean false',
    'bool_true'    => 'Boolean true',
    'zero'         => 'Number 0',
    'empty_string' => 'Empty string',
    'empty_array'  => 'Empty array',
    'null'         => 'Null',
    'safe'         => 'Safe value (inert object)',
];

// Build the editable rows: every detected function, plus any override that is
// not currently detected (so a saved override is never hidden).
$rows = $detected;
foreach ($overrides as $name => $value) {
    if (!array_key_exists($name, $rows)) {
        $rows[$name] = is_array($value) && isset($value['kind']) ? (string) $value['kind'] : 'safe';
    }
}
ksort($rows);

$overrideKind = static function (string $name) use ($overrides): string {
    $lower = strtolower($name);
    if (isset($overrides[$lower]) && is_array($overrides[$lower]) && isset($overrides[$lower]['kind'])) {
        return (string) $overrides[$lower]['kind'];
    }
    return '';
};
?>
<section class="admin-panel">
    <div class="section-heading">
        <p class="eyebrow">Appearance</p>
        <h1>Theme Bridge</h1>
        <p>When a ported WordPress theme calls a helper the runtime has no real implementation for, the bridge defines a safe fallback so the page renders instead of fataling. Below are the functions detected for the active theme <strong><?= htmlspecialchars((string) $activeTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong> and the fallback each one uses. Adjust any guess and save; choices are stored in <code>storage/theme-fallbacks.json</code> and apply to every theme.</p>
    </div>

    <?php if ($rows === []): ?>
        <p class="help-copy">No fallback functions are needed for the active theme — it runs entirely on the supported runtime surface.</p>
    <?php else: ?>
        <form method="post" action="/admin/theme-bridge">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

            <p class="help-copy"><strong><?= count($detected) ?></strong> function<?= count($detected) === 1 ? '' : 's' ?> detected for this theme.</p>

            <table class="bridge-table">
                <thead>
                    <tr>
                        <th>Function</th>
                        <th>Fallback behaviour</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $name => $guessedKind): ?>
                        <?php $current = $overrideKind((string) $name); ?>
                        <tr>
                            <td>
                                <code><?= htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>()</code>
                                <input type="hidden" name="fn_names[]" value="<?= htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                            </td>
                            <td>
                                <select name="fn_kinds[]">
                                    <?php foreach ($kindLabels as $kindValue => $label): ?>
                                        <?php
                                        // Show the smart guess as the "Smart default" hint when no override is set.
                                        $selected = $current === $kindValue;
                                        $suffix = ($kindValue === '' ? ' (currently: ' . htmlspecialchars((string) $guessedKind, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : '');
                                        ?>
                                        <option value="<?= htmlspecialchars((string) $kindValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?= $selected ? ' selected' : '' ?>>
                                            <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $suffix ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-actions">
                <button class="primary-button" type="submit">Save fallbacks</button>
            </div>
        </form>
    <?php endif; ?>
</section>

<style>
.bridge-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}
.bridge-table th,
.bridge-table td {
    text-align: left;
    padding: 0.5rem 0.6rem;
    border-bottom: 1px solid var(--panel-border);
    vertical-align: middle;
}
.bridge-table th {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
}
.bridge-table code {
    font-size: 0.85rem;
}
.bridge-table select {
    width: 100%;
    max-width: 22rem;
}
.form-actions {
    margin-top: 1rem;
}
</style>
