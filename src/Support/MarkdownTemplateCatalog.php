<?php
declare(strict_types=1);

namespace Cms\Support;

final class MarkdownTemplateCatalog
{
    public const SETTINGS_KEY = 'markdown_templates';

    public static function defaults(): array
    {
        return [
            [
                'name' => 'interactive',
                'markup' => "<!-- html:begin -->\n<!-- section.interactive-template -->\n{__markdown__}\n<!-- div.template-overlay --> <!-- strong --> <!-- Interactive template -->\n<!-- html:end -->",
            ],
            [
                'name' => 'rule',
                'markup' => "<!-- html:begin -->\n<!-- section.rule-wrapper -->\n{__markdown__}\n<!-- div.rule-accent --> <!-- span.rule-label --> <!-- Rule template -->\n<!-- html:end -->",
            ],
        ];
    }

    public static function decode(?string $json): array
    {
        $value = is_string($json) ? trim($json) : '';

        if ($value === '') {
            return self::defaults();
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return self::defaults();
        }

        return self::normalize($decoded);
    }

    public static function encode(array $templates): string
    {
        $json = json_encode(
            array_values(self::normalize($templates)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '[]';
    }

    public static function mapFromSettings(array $settings): array
    {
        return self::toMap(self::decode((string) ($settings[self::SETTINGS_KEY] ?? '')));
    }

    public static function toMap(array $templates): array
    {
        $map = [];

        foreach (self::normalize($templates) as $template) {
            $map[(string) $template['name']] = (string) $template['markup'];
        }

        return $map;
    }

    public static function normalize(array $templates): array
    {
        $normalized = [];

        if (self::isAssociative($templates)) {
            foreach ($templates as $name => $markup) {
                if (!is_string($markup)) {
                    continue;
                }

                $templateName = trim((string) $name);
                $templateMarkup = trim($markup);

                if ($templateName === '' || $templateMarkup === '') {
                    continue;
                }

                $normalized[$templateName] = [
                    'name' => $templateName,
                    'markup' => $templateMarkup,
                ];
            }

            return array_values($normalized);
        }

        foreach ($templates as $template) {
            if (!is_array($template)) {
                continue;
            }

            $templateName = trim((string) ($template['name'] ?? ''));
            $templateMarkup = trim((string) ($template['markup'] ?? ''));

            if ($templateName === '' || $templateMarkup === '') {
                continue;
            }

            $normalized[$templateName] = [
                'name' => $templateName,
                'markup' => $templateMarkup,
            ];
        }

        return array_values($normalized);
    }

    private static function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}