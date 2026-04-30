<?php
declare(strict_types=1);

namespace Cms\Repositories;

use Cms\Support\Markdown;
use PDO;

final class ContentRepository
{
    private array $termCache = [];

    public function __construct(private PDO $connection)
    {
    }

    public function getSettings(): array
    {
        $statement = $this->connection->query('SELECT key, value FROM settings');
        $settings = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }

        return $settings;
    }

    public function publishedPages(): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.template,
                p.markdown_math,
                p.use_marked,
                p.meta_title,
                p.meta_description,
                p.featured_image,
                p.sort_order,
                COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM pages p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.status = :status
             ORDER BY CASE WHEN p.slug = 'home' THEN -1 ELSE p.sort_order END ASC, p.published_at ASC, p.title ASC"
        );
        $statement->execute(['status' => 'published']);

        return $this->hydrateMany($statement->fetchAll(PDO::FETCH_ASSOC), 'page');
    }

    public function latestPosts(int $limit = 6): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.meta_title,
                p.markdown_math,
                 p.use_marked,
                p.meta_description,
                p.featured_image,
                     COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.status = :status
             ORDER BY p.published_at DESC
               LIMIT :limit"
        );
        $statement->bindValue(':status', 'published');
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->hydrateMany($statement->fetchAll(PDO::FETCH_ASSOC), 'post');
    }

    public function allPosts(): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.meta_title,
                p.markdown_math,
                 p.use_marked,
                p.meta_description,
                p.featured_image,
                     COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.status = :status
               ORDER BY p.published_at DESC"
        );
        $statement->execute(['status' => 'published']);

        return $this->hydrateMany($statement->fetchAll(PDO::FETCH_ASSOC), 'post');
    }

    public function termsByTaxonomy(string $taxonomy): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                t.id,
                t.taxonomy,
                t.slug,
                t.name,
                t.description,
                COUNT(p.id) AS content_count
             FROM terms t
             LEFT JOIN content_terms ct
                ON ct.term_id = t.id
                     AND ct.content_type = 'post'
             LEFT JOIN posts p
                ON p.id = ct.content_id
                     AND p.status = 'published'
             WHERE t.taxonomy = :taxonomy
             GROUP BY t.id, t.taxonomy, t.slug, t.name, t.description
                 ORDER BY t.name ASC"
        );
        $statement->execute(['taxonomy' => $taxonomy]);

        return array_map($this->hydrateTerm(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findTerm(string $taxonomy, string $slug): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT
                t.id,
                t.taxonomy,
                t.slug,
                t.name,
                t.description,
                COUNT(p.id) AS content_count
             FROM terms t
             LEFT JOIN content_terms ct
                ON ct.term_id = t.id
                     AND ct.content_type = 'post'
             LEFT JOIN posts p
                ON p.id = ct.content_id
                     AND p.status = 'published'
             WHERE t.taxonomy = :taxonomy
               AND t.slug = :slug
             GROUP BY t.id, t.taxonomy, t.slug, t.name, t.description
                         LIMIT 1"
        );
        $statement->execute([
            'taxonomy' => $taxonomy,
            'slug' => $slug,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateTerm($row) : null;
    }

    public function findPostsByTerm(string $taxonomy, string $slug): array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.meta_title,
                p.markdown_math,
                 p.use_marked,
                p.meta_description,
                p.featured_image,
                     COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM posts p
             INNER JOIN content_terms ct
                     ON ct.content_type = 'post'
                AND ct.content_id = p.id
             INNER JOIN terms t
                ON t.id = ct.term_id
             LEFT JOIN users u
                ON u.id = p.author_id
             WHERE p.status = :status
               AND t.taxonomy = :taxonomy
               AND t.slug = :slug
                         ORDER BY p.published_at DESC, ct.sort_order ASC"
        );
        $statement->execute([
            'status' => 'published',
            'taxonomy' => $taxonomy,
            'slug' => $slug,
        ]);

        return $this->hydrateMany($statement->fetchAll(PDO::FETCH_ASSOC), 'post');
    }

    public function findPageBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.template,
                p.markdown_math,
                p.use_marked,
                p.meta_title,
                p.meta_description,
                p.featured_image,
                p.sort_order,
                     COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM pages p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = :status
                 LIMIT 1"
        );
        $statement->execute([
            'slug' => $slug,
            'status' => 'published',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row, 'page') : null;
    }

    public function findPostBySlug(string $slug): ?array
    {
        $statement = $this->connection->prepare(
            "SELECT
                p.id,
                p.slug,
                p.title,
                p.excerpt,
                p.body_markdown,
                p.status,
                p.published_at,
                p.meta_title,
                p.markdown_math,
                 p.use_marked,
                p.meta_description,
                p.featured_image,
                     COALESCE(u.display_name, 'Editorial Team') AS author_name
             FROM posts p
             LEFT JOIN users u ON u.id = p.author_id
             WHERE p.slug = :slug AND p.status = :status
                 LIMIT 1"
        );
        $statement->execute([
            'slug' => $slug,
            'status' => 'published',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row, 'post') : null;
    }

    private function hydrateMany(array $rows, string $contentType): array
    {
        return array_map(fn (array $row): array => $this->hydrate($row, $contentType), $rows);
    }

    private function hydrate(array $row, string $contentType): array
    {
        $row['content_type'] = $contentType;
        $row['meta_title'] = (string) ($row['meta_title'] ?? $row['title'] ?? '');
        $row['meta_description'] = (string) ($row['meta_description'] ?? $row['excerpt'] ?? '');
        $row['author_name'] = (string) ($row['author_name'] ?? 'Editorial Team');
        $row['markdown_math'] = !empty($row['markdown_math']);
        $row['use_marked'] = !empty($row['use_marked']);
        $row['reading_minutes'] = $this->readingMinutes((string) ($row['body_markdown'] ?? ''));

        $terms = $this->termsFor($contentType, (int) ($row['id'] ?? 0));

        $row['terms'] = $terms;
        $row['categories'] = $terms['category'] ?? [];
        $row['tags'] = $terms['tag'] ?? [];
        $row['primary_category'] = $row['categories'][0] ?? null;
        $row['body_html'] = Markdown::toHtml((string) ($row['body_markdown'] ?? ''));

        return $row;
    }

    private function hydrateTerm(array $row): array
    {
        $row['content_count'] = (int) ($row['content_count'] ?? 0);

        return $row;
    }

    private function termsFor(string $contentType, int $contentId): array
    {
        $cacheKey = $contentType . ':' . $contentId;

        if (isset($this->termCache[$cacheKey])) {
            return $this->termCache[$cacheKey];
        }

        $statement = $this->connection->prepare(
            'SELECT t.id, t.taxonomy, t.slug, t.name, t.description
             FROM terms t
             INNER JOIN content_terms ct ON ct.term_id = t.id
             WHERE ct.content_type = :content_type
               AND ct.content_id = :content_id
             ORDER BY t.taxonomy ASC, ct.sort_order ASC, t.name ASC'
        );
        $statement->execute([
            'content_type' => $contentType,
            'content_id' => $contentId,
        ]);

        $groupedTerms = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $term) {
            $groupedTerms[(string) $term['taxonomy']][] = $term;
        }

        $this->termCache[$cacheKey] = $groupedTerms;

        return $groupedTerms;
    }

    private function readingMinutes(string $markdown): int
    {
        $plainText = preg_replace('/[`*_>#\[\]()!-]+/', ' ', $markdown) ?? $markdown;
        $wordCount = str_word_count($plainText);

        return max(1, (int) ceil($wordCount / 180));
    }
}
