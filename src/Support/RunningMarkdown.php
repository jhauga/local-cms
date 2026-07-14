<?php
declare(strict_types=1);

namespace Cms\Support;

/**
 * Parses a running markdown document: one markdown file that holds several
 * pages and/or posts separated by `---` lines.
 *
 * Document shape:
 *
 * - Optional opening top-matter between `---` fences. It can declare the
 *   document type (`pages`, `posts`, or `mix`), a shared `status`, reusable
 *   `groups` of tags and keywords, and `default-*` properties that fill in
 *   any metadata key an item leaves out.
 * - Items separated by a `---` line with an empty line before and after it.
 *   Separator lines inside fenced code blocks are ignored.
 * - Each item may carry quasi-top-matter: a fenced code block whose language
 *   identifier is `metadata`, holding `key: value` lines.
 *
 * Placeholders supported in top-matter defaults and metadata values:
 *
 * - `${groups.<name>}` expands to that group's tag or keyword list,
 *   depending on the key being resolved.
 * - `${h1.text}` / `${h2[1].text}` expand to the text of a heading in the
 *   item body.
 * - `${p.text}` / `${p[0].text}` expand to the text of a paragraph in the
 *   item body.
 */
final class RunningMarkdown
{
    private const LIST_KEYS = ['tags', 'keywords', 'categories'];

    /**
     * @param string $document     Raw running markdown source.
     * @param string $fallbackType Global default content type used when
     *                             nothing in the document decides one. This
     *                             is the admin `default_content_type` setting.
     * @return array{
     *     type: string,
     *     groups: array<string, array{tags: string[], keywords: string[]}>,
     *     defaults: array<string, string>,
     *     items: array<int, array<string, mixed>>,
     *     warnings: string[],
     *     errors: string[]
     * }
     */
    public static function parse(string $document, string $fallbackType = 'post'): array
    {
        $fallbackType = self::normalizeType($fallbackType);
        $fallbackType = in_array($fallbackType, ['page', 'post'], true) ? $fallbackType : 'post';
        $document = str_replace(["\r\n", "\r"], "\n", $document);
        $lines = explode("\n", $document);

        $warnings = [];
        $errors = [];

        [$front, $groups, $bodyStart] = self::parseTopMatter($lines, $warnings);

        $documentType = self::normalizeType((string) ($front['type'] ?? '')) ?? 'mix';
        $documentStatus = self::normalizeStatus((string) ($front['status'] ?? ''));

        $defaults = [];

        foreach ($front as $key => $value) {
            if (str_starts_with($key, 'default-')) {
                $defaults[substr($key, 8)] = $value;
            }
        }

        $sections = self::splitSections(array_slice($lines, $bodyStart));

        if ($sections === []) {
            $errors[] = 'The document has no content sections after the top-matter.';
        }

        $items = [];
        $lastType = null;

        foreach ($sections as $index => $sectionLines) {
            [$metadata, $bodyLines] = self::extractMetadataBlock($sectionLines);
            $body = trim(implode("\n", $bodyLines));

            if ($body === '' && $metadata === []) {
                continue;
            }

            $label = 'Section ' . ($index + 1);
            $item = self::resolveItem(
                $metadata,
                $body,
                $defaults,
                $groups,
                $documentType,
                $documentStatus,
                $lastType,
                $fallbackType,
                $label,
                $warnings
            );

            $lastType = $item['type'];
            $items[] = $item;
        }

        if ($items === [] && $errors === []) {
            $errors[] = 'No pages or posts could be read from the document.';
        }

        return [
            'type' => $documentType,
            'groups' => $groups,
            'defaults' => $defaults,
            'items' => $items,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * Reads the optional opening `---` top-matter, including the nested
     * `groups` block. Returns the flat properties, the parsed groups, and the
     * index of the first body line.
     *
     * @param string[] $lines
     * @param string[] $warnings
     * @return array{0: array<string, string>, 1: array<string, array{tags: string[], keywords: string[]}>, 2: int}
     */
    private static function parseTopMatter(array $lines, array &$warnings): array
    {
        $front = [];
        $groups = [];
        $count = count($lines);
        $start = 0;

        while ($start < $count && trim($lines[$start]) === '') {
            $start += 1;
        }

        if ($start >= $count || trim($lines[$start]) !== '---') {
            return [$front, $groups, 0];
        }

        $index = $start + 1;
        $closed = false;
        $groupName = null;
        $inGroups = false;

        for (; $index < $count; $index += 1) {
            $line = self::stripInlineNotes($lines[$index]);
            $trimmed = trim($line);

            if ($trimmed === '---') {
                $closed = true;
                $index += 1;
                break;
            }

            if ($trimmed === '') {
                continue;
            }

            if ($inGroups) {
                if ($trimmed === '}' || $trimmed === '},') {
                    if ($groupName !== null) {
                        $groupName = null;
                    } else {
                        $inGroups = false;
                    }

                    continue;
                }

                if (preg_match('/^([A-Za-z0-9_-]+):\s*\{$/', $trimmed, $match) === 1) {
                    $groupName = strtolower($match[1]);
                    $groups[$groupName] = ['tags' => [], 'keywords' => []];
                    continue;
                }

                if ($groupName !== null && preg_match('/^(tags|keywords):\s*(.*)$/i', $trimmed, $match) === 1) {
                    $groups[$groupName][strtolower($match[1])] = self::splitList($match[2]);
                    continue;
                }

                $warnings[] = 'Unrecognized line inside the groups block: "' . $trimmed . '".';
                continue;
            }

            if (preg_match('/^groups:\s*\{$/i', $trimmed) === 1) {
                $inGroups = true;
                continue;
            }

            if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $trimmed, $match) === 1) {
                $front[strtolower($match[1])] = trim($match[2]);
                continue;
            }

            $warnings[] = 'Unrecognized top-matter line: "' . $trimmed . '".';
        }

        if (!$closed) {
            $warnings[] = 'The opening top-matter block is never closed with "---". It was treated as content.';

            return [[], [], 0];
        }

        return [$front, $groups, $index];
    }

    /**
     * Removes HTML comments and shorthand annotations from a top-matter line
     * so annotated example documents still parse.
     */
    private static function stripInlineNotes(string $line): string
    {
        $line = preg_replace('/<!--.*?-->/', '', $line) ?? $line;
        $line = preg_replace('/\(\)[-=]>.*$/', '', $line) ?? $line;

        return $line;
    }

    /**
     * Splits body lines into item sections on `---` separator lines. A
     * separator only counts outside fenced code blocks and with an empty line
     * before and after it, so thematic breaks glued to text (which markdown
     * reads as setext headings) stay inside their section.
     *
     * @param string[] $lines
     * @return array<int, string[]>
     */
    private static function splitSections(array $lines): array
    {
        $sections = [];
        $current = [];
        $fence = null;
        $count = count($lines);

        for ($index = 0; $index < $count; $index += 1) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($fence === null && preg_match('/^(`{3,}|~{3,})/', $trimmed, $match) === 1) {
                $fence = $match[1];
                $current[] = $line;
                continue;
            }

            if ($fence !== null) {
                if (str_starts_with($trimmed, $fence)) {
                    $fence = null;
                }

                $current[] = $line;
                continue;
            }

            $previousBlank = $index === 0 || trim($lines[$index - 1]) === '';
            $nextBlank = $index + 1 >= $count || trim($lines[$index + 1]) === '';

            if ($trimmed === '---' && $previousBlank && $nextBlank) {
                if (trim(implode("\n", $current)) !== '') {
                    $sections[] = $current;
                }

                $current = [];
                continue;
            }

            $current[] = $line;
        }

        if (trim(implode("\n", $current)) !== '') {
            $sections[] = $current;
        }

        return $sections;
    }

    /**
     * Pulls the first `metadata` fenced code block out of a section and
     * parses its `key: value` lines. Lines without a key continue the
     * previous value.
     *
     * @param string[] $sectionLines
     * @return array{0: array<string, string>, 1: string[]}
     */
    private static function extractMetadataBlock(array $sectionLines): array
    {
        $metadata = [];
        $body = [];
        $inBlock = false;
        $captured = false;
        $fence = null;
        $lastKey = null;

        foreach ($sectionLines as $line) {
            $trimmed = trim($line);

            if (!$inBlock && !$captured && preg_match('/^(`{3,}|~{3,})\s*metadata\s*$/i', $trimmed, $match) === 1) {
                $inBlock = true;
                $fence = $match[1];
                continue;
            }

            if ($inBlock) {
                if ($fence !== null && str_starts_with($trimmed, $fence)) {
                    $inBlock = false;
                    $captured = true;
                    $lastKey = null;
                    continue;
                }

                if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $trimmed, $match) === 1) {
                    $lastKey = strtolower($match[1]);
                    $metadata[$lastKey] = trim($match[2]);
                } elseif ($lastKey !== null && $trimmed !== '') {
                    $metadata[$lastKey] = trim($metadata[$lastKey] . ' ' . $trimmed);
                }

                continue;
            }

            $body[] = $line;
        }

        return [$metadata, $body];
    }

    /**
     * Resolves one section into a content item, walking the fallback chain
     * for each field: item metadata, then top-matter defaults, then document
     * level values, then the global default type.
     *
     * @param array<string, string> $metadata
     * @param array<string, string> $defaults
     * @param array<string, array{tags: string[], keywords: string[]}> $groups
     * @param string[] $warnings
     * @return array<string, mixed>
     */
    private static function resolveItem(
        array $metadata,
        string $body,
        array $defaults,
        array $groups,
        string $documentType,
        ?string $documentStatus,
        ?string $lastType,
        string $fallbackType,
        string $label,
        array &$warnings
    ): array {
        $value = static function (string $key) use ($metadata, $defaults): ?string {
            $raw = $metadata[$key] ?? $defaults[$key] ?? null;

            return $raw !== null && trim($raw) !== '' ? trim($raw) : null;
        };

        $type = self::normalizeType((string) ($metadata['type'] ?? ''));

        if ($type === null && isset($metadata['type']) && trim($metadata['type']) !== '') {
            $warnings[] = $label . ': unknown type "' . trim($metadata['type']) . '" was ignored.';
        }

        if ($type === 'mix') {
            $warnings[] = $label . ': an item cannot be type "mix", the type was resolved from the document instead.';
            $type = null;
        }

        if ($type === null && in_array($documentType, ['page', 'post'], true)) {
            $type = $documentType;
        }

        $type ??= $lastType;
        $defaultType = self::normalizeType((string) ($defaults['type'] ?? ''));
        $type ??= $defaultType !== 'mix' ? $defaultType : null;
        $type ??= $fallbackType;

        $status = self::normalizeStatus((string) ($value('status') ?? ''));

        if ($status === null && $value('status') !== null) {
            $warnings[] = $label . ': unknown status "' . $value('status') . '" was treated as draft.';
            $status = 'draft';
        }

        $status ??= $documentStatus;
        $status ??= 'draft';

        $title = self::resolvePlaceholders((string) ($value('title') ?? ''), $groups, $body, 'title');

        if ($title === '') {
            $title = self::headingText($body, 1, 0) ?? '';
        }

        if ($title === '') {
            $warnings[] = $label . ': no title could be resolved, "Untitled" was used.';
            $title = 'Untitled';
        }

        $description = self::resolvePlaceholders((string) ($value('description') ?? ''), $groups, $body, 'description');

        $singular = ['tags' => 'tag', 'keywords' => 'keyword', 'categories' => 'category'];
        $lists = [];

        foreach (self::LIST_KEYS as $key) {
            $raw = $value($key) ?? $value($singular[$key]) ?? '';
            $lists[$key] = self::resolveList($raw, $groups, $body, $key);
        }

        return [
            'type' => $type,
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'tags' => $lists['tags'],
            'keywords' => $lists['keywords'],
            'categories' => $lists['categories'],
            'body' => $body,
            'metadata' => $metadata,
        ];
    }

    /**
     * Resolves a comma separated value that may contain `${groups.<name>}`
     * references into a unique list of strings.
     *
     * @param array<string, array{tags: string[], keywords: string[]}> $groups
     * @return string[]
     */
    private static function resolveList(string $raw, array $groups, string $body, string $context): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $resolved = self::resolvePlaceholders($raw, $groups, $body, $context);

        return self::splitList($resolved);
    }

    /**
     * Expands `${...}` placeholders inside a value. Group references pick
     * the list matching the key being resolved: `keywords` when resolving a
     * keywords value, `tags` for everything else.
     *
     * @param array<string, array{tags: string[], keywords: string[]}> $groups
     */
    private static function resolvePlaceholders(string $value, array $groups, string $body, string $context): string
    {
        return trim((string) preg_replace_callback(
            '/\$\{([^}]+)\}/',
            static function (array $match) use ($groups, $body, $context): string {
                $expression = trim($match[1]);

                if (preg_match('/^groups\.([A-Za-z0-9_-]+)$/i', $expression, $ref) === 1) {
                    $group = $groups[strtolower($ref[1])] ?? null;

                    if ($group === null) {
                        return '';
                    }

                    $list = $context === 'keywords' ? $group['keywords'] : $group['tags'];

                    return implode(', ', $list);
                }

                if (preg_match('/^h([1-6])(?:\[(\d+)\])?\.text$/i', $expression, $ref) === 1) {
                    return RunningMarkdown::headingText($body, (int) $ref[1], (int) ($ref[2] ?? 0)) ?? '';
                }

                if (preg_match('/^p(?:\[(\d+)\])?\.text$/i', $expression, $ref) === 1) {
                    return RunningMarkdown::paragraphText($body, (int) ($ref[1] ?? 0)) ?? '';
                }

                return '';
            },
            $value
        ));
    }

    /**
     * Text of the nth heading at the given level, ignoring fenced code
     * blocks. Inline markdown and link syntax are reduced to plain text.
     */
    public static function headingText(string $body, int $level, int $index): ?string
    {
        $found = 0;
        $fence = null;

        foreach (explode("\n", $body) as $line) {
            $trimmed = trim($line);

            if ($fence === null && preg_match('/^(`{3,}|~{3,})/', $trimmed, $match) === 1) {
                $fence = $match[1];
                continue;
            }

            if ($fence !== null) {
                if (str_starts_with($trimmed, $fence)) {
                    $fence = null;
                }

                continue;
            }

            if (preg_match('/^#{' . $level . '}\s+(.*)$/', $trimmed, $match) === 1) {
                if ($found === $index) {
                    return self::plainText($match[1]);
                }

                $found += 1;
            }
        }

        return null;
    }

    /**
     * Text of the nth paragraph, skipping headings, lists, blockquotes,
     * tables, HTML comments, and fenced code blocks. Consecutive paragraph
     * lines are joined with a space.
     */
    public static function paragraphText(string $body, int $index): ?string
    {
        $paragraphs = [];
        $current = [];
        $fence = null;

        $flush = static function () use (&$paragraphs, &$current): void {
            if ($current !== []) {
                $paragraphs[] = implode(' ', $current);
                $current = [];
            }
        };

        foreach (explode("\n", $body) as $line) {
            $trimmed = trim($line);

            if ($fence === null && preg_match('/^(`{3,}|~{3,})/', $trimmed, $match) === 1) {
                $fence = $match[1];
                $flush();
                continue;
            }

            if ($fence !== null) {
                if (str_starts_with($trimmed, $fence)) {
                    $fence = null;
                }

                continue;
            }

            if ($trimmed === '') {
                $flush();
                continue;
            }

            if (preg_match('/^(#{1,6}\s|[-*+]\s|\d+[.)]\s|>|\||<!--|---$)/', $trimmed) === 1) {
                $flush();
                continue;
            }

            $current[] = $trimmed;
        }

        $flush();

        $paragraph = $paragraphs[$index] ?? null;

        return $paragraph !== null ? self::plainText($paragraph) : null;
    }

    /**
     * Reduces inline markdown to plain text for titles and descriptions.
     */
    private static function plainText(string $value): string
    {
        $value = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $value) ?? $value;
        $value = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $value) ?? $value;
        $value = preg_replace('/<!--.*?-->/', '', $value) ?? $value;
        $value = str_replace(['**', '__', '~~', '`'], '', $value);

        return trim($value);
    }

    /**
     * @return string[]
     */
    private static function splitList(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, static fn (string $part): bool => $part !== '');

        return array_values(array_unique($parts));
    }

    private static function normalizeType(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'page', 'pages' => 'page',
            'post', 'posts' => 'post',
            'mix', 'mixed' => 'mix',
            default => null,
        };
    }

    private static function normalizeStatus(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'publish', 'published' => 'published',
            'draft' => 'draft',
            default => null,
        };
    }
}
