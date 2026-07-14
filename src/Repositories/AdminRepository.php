<?php
declare(strict_types=1);

namespace Cms\Repositories;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class AdminRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function findUserByEmail(string $email): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, password_hash, display_name FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findUserById(int $userId): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, email, password_hash, display_name FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findDefaultUser(): ?array
    {
        $adminUser = $this->findUserByEmail('admin@example.com');

        if ($adminUser !== null) {
            return $adminUser;
        }

        $statement = $this->connection->query(
            'SELECT id, email, password_hash, display_name FROM users ORDER BY id ASC LIMIT 1'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function getSettings(): array
    {
        $statement = $this->connection->query('SELECT key, value FROM settings ORDER BY key ASC');
        $settings = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[(string) $row['key']] = (string) $row['value'];
        }

        return $settings;
    }

    public function updateSettings(array $settings): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO settings (key, value) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );

        foreach ($settings as $key => $value) {
            $statement->execute([
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    public function dashboardSummary(): array
    {
        return [
            'pages' => $this->count('pages'),
            'posts' => $this->count('posts'),
            'categories' => $this->countTerms('category'),
            'tags' => $this->countTerms('tag'),
            'drafts' => $this->countDrafts(),
        ];
    }

    public function listContent(string $type, ?int $limit = null): array
    {
        $table = $this->resolveContentTable($type);
        $limitSql = $limit !== null ? ' LIMIT :limit' : '';

        $statement = $this->connection->prepare(
            "SELECT
                c.id,
                c.slug,
                c.title,
                c.excerpt,
                c.status,
                c.published_at,
                c.updated_at,
                c.markdown_math,
                COALESCE(u.display_name, 'Admin') AS author_name,
                " . $this->selectContentExtras($type) . "
             FROM {$table} c
             LEFT JOIN users u ON u.id = c.author_id
             ORDER BY c.updated_at DESC, c.created_at DESC{$limitSql}"
        );

        if ($limit !== null) {
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row): array => $this->hydrateContentRow($row, $type), $rows);
    }

    public function findContent(string $type, int $id): ?array
    {
        $table = $this->resolveContentTable($type);

        $statement = $this->connection->prepare(
            "SELECT
                c.id,
                c.slug,
                c.title,
                c.excerpt,
                c.body_markdown,
                c.status,
                c.published_at,
                c.meta_title,
                c.markdown_math,
                c.meta_description,
                c.featured_image,
                c.author_id,
                COALESCE(u.display_name, 'Admin') AS author_name,
                " . $this->selectContentExtras($type) . "
             FROM {$table} c
             LEFT JOIN users u ON u.id = c.author_id
             WHERE c.id = :id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateContentRow($row, $type) : null;
    }

    public function saveContent(string $type, array $data, array $termIds, ?int $id = null): int
    {
        $table = $this->resolveContentTable($type);
        $termIds = $this->filterExistingTermIds($termIds);

        $this->connection->beginTransaction();

        try {
            if ($id === null) {
                if ($type === 'page') {
                    $statement = $this->connection->prepare(
                        "INSERT INTO {$table} (
                            slug,
                            title,
                            excerpt,
                            body_markdown,
                            markdown_math,
                            use_marked,
                            status,
                            published_at,
                            author_id,
                            template,
                            meta_title,
                            meta_description,
                            featured_image,
                            sort_order
                        ) VALUES (
                            :slug,
                            :title,
                            :excerpt,
                            :body_markdown,
                            :markdown_math,
                            :use_marked,
                            :status,
                            :published_at,
                            :author_id,
                            :template,
                            :meta_title,
                            :meta_description,
                            :featured_image,
                            :sort_order
                        )"
                    );
                } else {
                    $statement = $this->connection->prepare(
                        "INSERT INTO {$table} (
                            slug,
                            title,
                            excerpt,
                            body_markdown,
                            markdown_math,
                            use_marked,
                            status,
                            published_at,
                            author_id,
                            meta_title,
                            meta_description,
                            featured_image
                        ) VALUES (
                            :slug,
                            :title,
                            :excerpt,
                            :body_markdown,
                            :markdown_math,
                            :use_marked,
                            :status,
                            :published_at,
                            :author_id,
                            :meta_title,
                            :meta_description,
                            :featured_image
                        )"
                    );
                }

                $statement->execute($this->contentParameters($type, $data));
                $id = (int) $this->connection->lastInsertId();
            } else {
                if ($type === 'page') {
                    $statement = $this->connection->prepare(
                        "UPDATE {$table}
                         SET slug = :slug,
                             title = :title,
                             excerpt = :excerpt,
                             body_markdown = :body_markdown,
                             markdown_math = :markdown_math,
                             use_marked = :use_marked,
                             status = :status,
                             published_at = :published_at,
                             author_id = :author_id,
                             template = :template,
                             meta_title = :meta_title,
                             meta_description = :meta_description,
                             featured_image = :featured_image,
                             sort_order = :sort_order,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id"
                    );
                } else {
                    $statement = $this->connection->prepare(
                        "UPDATE {$table}
                         SET slug = :slug,
                             title = :title,
                             excerpt = :excerpt,
                             body_markdown = :body_markdown,
                             markdown_math = :markdown_math,
                             use_marked = :use_marked,
                             status = :status,
                             published_at = :published_at,
                             author_id = :author_id,
                             meta_title = :meta_title,
                             meta_description = :meta_description,
                             featured_image = :featured_image,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE id = :id"
                    );
                }

                $parameters = $this->contentParameters($type, $data);
                $parameters['id'] = $id;
                $statement->execute($parameters);
            }

            $this->syncContentTerms($type, $id, $termIds);
            $this->connection->commit();

            return $id;
        } catch (\Throwable $throwable) {
            $this->connection->rollBack();

            throw $throwable;
        }
    }

    public function deleteContent(string $type, int $id): void
    {
        $table = $this->resolveContentTable($type);
        $statement = $this->connection->prepare('DELETE FROM content_terms WHERE content_type = :content_type AND content_id = :content_id');
        $statement->execute([
            'content_type' => $type,
            'content_id' => $id,
        ]);

        $deleteContent = $this->connection->prepare("DELETE FROM {$table} WHERE id = :id");
        $deleteContent->execute(['id' => $id]);
    }

    public function listTerms(?string $taxonomy = null): array
    {
        if ($taxonomy === null) {
            $statement = $this->connection->query(
                "SELECT
                    t.id,
                    t.taxonomy,
                    t.slug,
                    t.name,
                    t.description,
                    COUNT(ct.id) AS assignment_count
                 FROM terms t
                 LEFT JOIN content_terms ct ON ct.term_id = t.id
                 GROUP BY t.id, t.taxonomy, t.slug, t.name, t.description
                 ORDER BY t.taxonomy ASC, t.name ASC"
            );

            return array_map(fn (array $row): array => $this->hydrateTermRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
        }

        $statement = $this->connection->prepare(
            "SELECT
                t.id,
                t.taxonomy,
                t.slug,
                t.name,
                t.description,
                COUNT(ct.id) AS assignment_count
             FROM terms t
             LEFT JOIN content_terms ct ON ct.term_id = t.id
             WHERE t.taxonomy = :taxonomy
             GROUP BY t.id, t.taxonomy, t.slug, t.name, t.description
             ORDER BY t.name ASC"
        );
        $statement->execute(['taxonomy' => $taxonomy]);

        return array_map(fn (array $row): array => $this->hydrateTermRow($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listTermsGrouped(): array
    {
        $grouped = [
            'category' => [],
            'tag' => [],
        ];

        foreach ($this->listTerms() as $term) {
            $grouped[(string) $term['taxonomy']][] = $term;
        }

        return $grouped;
    }

    public function findTermById(int $id): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, taxonomy, slug, name, description FROM terms WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrateTermRow($row) : null;
    }

    public function saveTerm(array $data, ?int $id = null): int
    {
        if ($id === null) {
            $statement = $this->connection->prepare(
                'INSERT INTO terms (taxonomy, slug, name, description) VALUES (:taxonomy, :slug, :name, :description)'
            );
            $statement->execute($data);

            return (int) $this->connection->lastInsertId();
        }

        $statement = $this->connection->prepare(
            'UPDATE terms
             SET taxonomy = :taxonomy,
                 slug = :slug,
                 name = :name,
                 description = :description
             WHERE id = :id'
        );
        $data['id'] = $id;
        $statement->execute($data);

        return $id;
    }

    public function deleteTerm(int $id): void
    {
        $statement = $this->connection->prepare('DELETE FROM terms WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function findContentIdBySlug(string $type, string $slug): ?int
    {
        $table = $this->resolveContentTable($type);
        $statement = $this->connection->prepare("SELECT id FROM {$table} WHERE slug = :slug LIMIT 1");
        $statement->execute(['slug' => $slug]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function slugExists(string $type, string $slug, ?int $excludeId = null): bool
    {
        $table = $this->resolveContentTable($type);
        $sql = "SELECT 1 FROM {$table} WHERE slug = :slug";
        $parameters = ['slug' => $slug];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $parameters['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function termSlugExists(string $taxonomy, string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM terms WHERE taxonomy = :taxonomy AND slug = :slug';
        $parameters = [
            'taxonomy' => $taxonomy,
            'slug' => $slug,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $parameters['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function filterExistingTermIds(array $termIds): array
    {
        $termIds = array_values(array_unique(array_filter(array_map(static fn (mixed $value): int => (int) $value, $termIds), static fn (int $id): bool => $id > 0)));

        if ($termIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($termIds), '?'));
        $statement = $this->connection->prepare("SELECT id FROM terms WHERE id IN ({$placeholders})");
        $statement->execute($termIds);

        return array_map(static fn (mixed $value): int => (int) $value, $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function hydrateContentRow(array $row, string $type): array
    {
        $row['content_type'] = $type;
        $row['markdown_math'] = !empty($row['markdown_math']);
        $row['use_marked'] = !empty($row['use_marked']);
        $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
        $row['term_ids'] = [];
        $terms = $this->termsForContent($type, (int) $row['id']);
        $row['categories'] = $terms['category'] ?? [];
        $row['tags'] = $terms['tag'] ?? [];
        $row['term_ids'] = array_map(static fn (array $term): int => (int) $term['id'], array_merge($row['categories'], $row['tags']));

        return $row;
    }

    private function hydrateTermRow(array $row): array
    {
        $row['assignment_count'] = (int) ($row['assignment_count'] ?? 0);

        return $row;
    }

    private function termsForContent(string $type, int $contentId): array
    {
        $statement = $this->connection->prepare(
            'SELECT t.id, t.taxonomy, t.slug, t.name, t.description
             FROM terms t
             INNER JOIN content_terms ct ON ct.term_id = t.id
             WHERE ct.content_type = :content_type AND ct.content_id = :content_id
             ORDER BY t.taxonomy ASC, ct.sort_order ASC, t.name ASC'
        );
        $statement->execute([
            'content_type' => $type,
            'content_id' => $contentId,
        ]);

        $grouped = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $term) {
            $grouped[(string) $term['taxonomy']][] = $term;
        }

        return $grouped;
    }

    private function syncContentTerms(string $type, int $contentId, array $termIds): void
    {
        $delete = $this->connection->prepare('DELETE FROM content_terms WHERE content_type = :content_type AND content_id = :content_id');
        $delete->execute([
            'content_type' => $type,
            'content_id' => $contentId,
        ]);

        if ($termIds === []) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO content_terms (content_type, content_id, term_id, sort_order) VALUES (:content_type, :content_id, :term_id, :sort_order)'
        );

        foreach (array_values($termIds) as $index => $termId) {
            $insert->execute([
                'content_type' => $type,
                'content_id' => $contentId,
                'term_id' => $termId,
                'sort_order' => $index,
            ]);
        }
    }

    private function count(string $table): int
    {
        return (int) $this->connection->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    private function countTerms(string $taxonomy): int
    {
        $statement = $this->connection->prepare('SELECT COUNT(*) FROM terms WHERE taxonomy = :taxonomy');
        $statement->execute(['taxonomy' => $taxonomy]);

        return (int) $statement->fetchColumn();
    }

    private function countDrafts(): int
    {
        $pages = (int) $this->connection->query("SELECT COUNT(*) FROM pages WHERE status = 'draft'")->fetchColumn();
        $posts = (int) $this->connection->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn();

        return $pages + $posts;
    }

    private function contentParameters(string $type, array $data): array
    {
        $parameters = [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'body_markdown' => $data['body_markdown'],
            'markdown_math' => !empty($data['markdown_math']) ? 1 : 0,
            'use_marked' => !empty($data['use_marked']) ? 1 : 0,
            'status' => $data['status'],
            'published_at' => $data['published_at'],
            'author_id' => $data['author_id'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'featured_image' => $data['featured_image'],
        ];

        if ($type === 'page') {
            $parameters['template'] = $data['template'];
            $parameters['sort_order'] = $data['sort_order'];
        }

        return $parameters;
    }

    private function selectContentExtras(string $type): string
    {
        return match ($type) {
            'page' => "c.template, c.sort_order, c.meta_title, c.meta_description, c.featured_image, c.markdown_math, c.use_marked",
            'post' => "'single' AS template, 0 AS sort_order, c.meta_title, c.meta_description, c.featured_image, c.markdown_math, c.use_marked",
            default => throw new InvalidArgumentException('Unsupported content type.'),
        };
    }

    private function resolveContentTable(string $type): string
    {
        return match ($type) {
            'page' => 'pages',
            'post' => 'posts',
            default => throw new InvalidArgumentException('Unsupported content type.'),
        };
    }
}
