<?php
declare(strict_types=1);

namespace Cms\Support;

use Cms\Repositories\AdminRepository;

/**
 * Applies a parsed running markdown document to the content tables. Each
 * item becomes a page or post. An item whose slug already exists for its
 * type updates that record, so re-importing a growing running markdown file
 * stays idempotent. Missing tag and category terms are created on the fly.
 *
 * Keywords are parsed and reported but not persisted yet; the content model
 * has no keywords column at this time.
 */
final class RunningMarkdownImporter
{
    public function __construct(private AdminRepository $repository)
    {
    }

    /**
     * @param array{items: array<int, array<string, mixed>>, warnings: string[], errors: string[]} $parsed
     * @return array{
     *     created: int,
     *     updated: int,
     *     items: array<int, array<string, mixed>>,
     *     warnings: string[]
     * }
     */
    public function import(array $parsed, int $authorId): array
    {
        $termIdsBySlug = $this->loadTermIndex();
        $report = [
            'created' => 0,
            'updated' => 0,
            'items' => [],
            'warnings' => $parsed['warnings'],
        ];

        foreach ($parsed['items'] as $index => $item) {
            $type = (string) $item['type'];
            $slug = $this->slugify((string) $item['title']);

            if ($slug === '') {
                $report['warnings'][] = 'Item ' . ($index + 1) . ' was skipped: its title produced an empty slug.';
                continue;
            }

            if ((string) $item['body'] === '') {
                $report['warnings'][] = sprintf('"%s" was skipped: it has no body content.', (string) $item['title']);
                continue;
            }

            $termIds = array_merge(
                $this->resolveTerms('tag', (array) $item['tags'], $termIdsBySlug),
                $this->resolveTerms('category', (array) $item['categories'], $termIdsBySlug)
            );

            $existingId = $this->repository->findContentIdBySlug($type, $slug);
            $payload = $this->contentPayload($item, $slug, $authorId);
            $contentId = $this->repository->saveContent($type, $payload, $termIds, $existingId);

            $action = $existingId === null ? 'created' : 'updated';
            $report[$action] += 1;
            $report['items'][] = [
                'id' => $contentId,
                'title' => (string) $item['title'],
                'slug' => $slug,
                'type' => $type,
                'status' => (string) $item['status'],
                'tags' => (array) $item['tags'],
                'categories' => (array) $item['categories'],
                'keywords' => (array) $item['keywords'],
                'action' => $action,
            ];
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function contentPayload(array $item, string $slug, int $authorId): array
    {
        $status = (string) $item['status'];
        $description = (string) $item['description'];

        $payload = [
            'slug' => $slug,
            'title' => (string) $item['title'],
            'excerpt' => $description,
            'body_markdown' => (string) $item['body'],
            'markdown_math' => false,
            'use_marked' => false,
            'status' => $status,
            'published_at' => $status === 'published' ? gmdate('Y-m-d H:i:s') : null,
            'author_id' => $authorId,
            'meta_title' => (string) $item['title'],
            'meta_description' => $description,
            'featured_image' => '',
        ];

        if ((string) $item['type'] === 'page') {
            $payload['template'] = 'page';
            $payload['sort_order'] = 0;
        }

        return $payload;
    }

    /**
     * Maps term names to ids for one taxonomy, creating terms that do not
     * exist yet.
     *
     * @param string[] $names
     * @param array<string, array<string, int>> $termIdsBySlug
     * @return int[]
     */
    private function resolveTerms(string $taxonomy, array $names, array &$termIdsBySlug): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            $slug = $this->slugify($name);

            if ($slug === '') {
                continue;
            }

            if (!isset($termIdsBySlug[$taxonomy][$slug])) {
                $termIdsBySlug[$taxonomy][$slug] = $this->repository->saveTerm([
                    'taxonomy' => $taxonomy,
                    'slug' => $slug,
                    'name' => $name,
                    'description' => '',
                ]);
            }

            $ids[] = $termIdsBySlug[$taxonomy][$slug];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function loadTermIndex(): array
    {
        $index = ['tag' => [], 'category' => []];

        foreach ($this->repository->listTerms() as $term) {
            $index[(string) $term['taxonomy']][(string) $term['slug']] = (int) $term['id'];
        }

        return $index;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
