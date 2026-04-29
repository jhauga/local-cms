<?php
declare(strict_types=1);

namespace Cms\Support;

final class Markdown
{
    private static bool $vendorLoaded = false;

    public static function toHtml(string $markdown): string
    {
        $markdown = self::normalize($markdown);

        if ($markdown === '') {
            return '';
        }

        $commonMarkHtml = self::convertWithCommonMark($markdown);

        if ($commonMarkHtml !== null) {
            return $commonMarkHtml;
        }

        $lines = preg_split('/\r\n|\n|\r/', $markdown) ?: [];

        return self::parseBlocks($lines);
    }

    private static function normalize(string $markdown): string
    {
        $markdown = str_replace(["\\r\\n", "\\n", "\\r"], ["\r\n", "\n", "\r"], $markdown);

        return trim($markdown);
    }

    private static function convertWithCommonMark(string $markdown): ?string
    {
        self::loadVendorAutoload();

        if (!class_exists('League\\CommonMark\\MarkdownConverter')) {
            return null;
        }

        $converter = new \League\CommonMark\MarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $result = method_exists($converter, 'convert')
            ? $converter->convert($markdown)
            : $converter->convertToHtml($markdown);

        return trim((string) $result);
    }

    private static function loadVendorAutoload(): void
    {
        if (self::$vendorLoaded) {
            return;
        }

        self::$vendorLoaded = true;

        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';

        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    private static function parseBlocks(array $lines): string
    {
        $html = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            $line = $lines[$index];
            $trimmed = trim($line);

            if ($trimmed === '') {
                $index++;
                continue;
            }

            if (preg_match('/^(```|~~~)\s*([A-Za-z0-9_-]+)?\s*$/', $trimmed, $matches) === 1) {
                [$html[], $index] = self::parseCodeBlock($lines, $index, $matches[1], $matches[2] ?? '');
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches) === 1) {
                $level = strlen($matches[1]);
                $html[] = sprintf('<h%d>%s</h%d>', $level, self::parseInlines($matches[2]), $level);
                $index++;
                continue;
            }

            if (preg_match('/^([-*_])(?:\s*\1){2,}\s*$/', $trimmed) === 1) {
                $html[] = '<hr>';
                $index++;
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed) === 1) {
                [$html[], $index] = self::parseBlockquote($lines, $index);
                continue;
            }

            if (preg_match('/^([-+*])\s+(.+)$/', $trimmed) === 1) {
                [$html[], $index] = self::parseList($lines, $index, false);
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed) === 1) {
                [$html[], $index] = self::parseList($lines, $index, true);
                continue;
            }

            [$html[], $index] = self::parseParagraph($lines, $index);
        }

        return implode("\n", array_filter($html, static fn (string $block): bool => $block !== ''));
    }

    private static function parseCodeBlock(array $lines, int $index, string $fence, string $language): array
    {
        $index++;
        $codeLines = [];
        $count = count($lines);

        while ($index < $count) {
            if (trim($lines[$index]) === $fence) {
                $index++;
                break;
            }

            $codeLines[] = rtrim($lines[$index], "\r");
            $index++;
        }

        $classAttribute = $language !== ''
            ? ' class="language-' . htmlspecialchars($language, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            : '';

        $html = '<pre><code' . $classAttribute . '>'
            . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</code></pre>';

        return [$html, $index];
    }

    private static function parseBlockquote(array $lines, int $index): array
    {
        $quoteLines = [];
        $count = count($lines);

        while ($index < $count) {
            $trimmed = trim($lines[$index]);

            if ($trimmed === '') {
                $quoteLines[] = '';
                $index++;
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $matches) !== 1) {
                break;
            }

            $quoteLines[] = $matches[1];
            $index++;
        }

        $inner = self::parseBlocks($quoteLines);

        return ['<blockquote>' . "\n" . $inner . "\n" . '</blockquote>', $index];
    }

    private static function parseList(array $lines, int $index, bool $ordered): array
    {
        $tag = $ordered ? 'ol' : 'ul';
        $pattern = $ordered ? '/^\d+\.\s+(.+)$/' : '/^[-+*]\s+(.+)$/';
        $items = [];
        $count = count($lines);

        while ($index < $count) {
            $trimmed = trim($lines[$index]);

            if ($trimmed === '') {
                $index++;
                break;
            }

            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                break;
            }

            $item = $matches[1];
            $index++;

            while ($index < $count) {
                $continuation = trim($lines[$index]);

                if ($continuation === '') {
                    break;
                }

                if (self::startsNewBlock($continuation)) {
                    break;
                }

                $item .= ' ' . $continuation;
                $index++;
            }

            $items[] = '<li>' . self::parseInlines($item) . '</li>';
        }

        $html = '<' . $tag . '>' . "\n" . implode("\n", $items) . "\n" . '</' . $tag . '>';

        return [$html, $index];
    }

    private static function parseParagraph(array $lines, int $index): array
    {
        $paragraph = [];
        $count = count($lines);

        while ($index < $count) {
            $trimmed = trim($lines[$index]);

            if ($trimmed === '') {
                break;
            }

            if (self::startsNewBlock($trimmed)) {
                break;
            }

            $paragraph[] = $trimmed;
            $index++;
        }

        return ['<p>' . self::parseInlines(implode(' ', $paragraph)) . '</p>', $index];
    }

    private static function startsNewBlock(string $line): bool
    {
        return preg_match('/^(#{1,6})\s+/', $line) === 1
            || preg_match('/^(```|~~~)\s*/', $line) === 1
            || preg_match('/^>\s?/', $line) === 1
            || preg_match('/^[-+*]\s+/', $line) === 1
            || preg_match('/^\d+\.\s+/', $line) === 1
            || preg_match('/^([-*_])(?:\s*\1){2,}\s*$/', $line) === 1;
    }

    private static function parseInlines(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $placeholders = [];

        $text = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$placeholders): string {
            return self::storePlaceholder($placeholders, '<code>' . $matches[1] . '</code>');
        }, $text) ?? $text;

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $matches) use (&$placeholders): string {
            $href = html_entity_decode($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (!self::isSafeHref($href)) {
                return $matches[1];
            }

            $html = '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $matches[1] . '</a>';

            return self::storePlaceholder($placeholders, $html);
        }, $text) ?? $text;

        $patterns = [
            '/\*\*([^*]+)\*\*/' => '<strong>$1</strong>',
            '/__([^_]+)__/' => '<strong>$1</strong>',
            '/(?<!\*)\*([^*]+)\*(?!\*)/' => '<em>$1</em>',
            '/(?<!_)_([^_]+)_(?!_)/' => '<em>$1</em>',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return strtr($text, $placeholders);
    }

    private static function storePlaceholder(array &$placeholders, string $html): string
    {
        $key = '@@md' . count($placeholders) . '@@';
        $placeholders[$key] = $html;

        return $key;
    }

    private static function isSafeHref(string $href): bool
    {
        $href = trim($href);

        if ($href === '' || preg_match('/^(javascript|data|vbscript):/i', $href) === 1) {
            return false;
        }

        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            return !str_contains($href, ':');
        }

        return in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
    }
}
