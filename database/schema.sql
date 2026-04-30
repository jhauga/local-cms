CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    excerpt TEXT,
    body_markdown TEXT NOT NULL,
    markdown_math INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft',
    published_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    excerpt TEXT,
    body_markdown TEXT NOT NULL,
    markdown_math INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft',
    published_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO settings (key, value) VALUES
    ('site_name', 'Local CMS'),
    ('site_tagline', 'Simple publishing with WordPress-shaped theme templates.');

INSERT OR IGNORE INTO users (email, password_hash, display_name) VALUES
    ('admin@example.com', '$2y$10$kRV15q/TH3ZIK18EOHm3ie2q7gwJF9xFmdu2J2Jhuk9DvICT/CaZ6', 'Admin');

INSERT OR IGNORE INTO pages (slug, title, excerpt, body_markdown, status, published_at) VALUES
    (
        'home',
        'Local CMS',
        'A compact publishing system with a WordPress-ready theme structure.',
        '# Publish without the framework weight\n\nLocal CMS starts with a lightweight PHP core, SQLite storage, and theme templates that already mirror familiar WordPress files.\n\n- Keep the application simple\n- Keep the theme portable\n- Grow features in clean slices',
        'published',
        CURRENT_TIMESTAMP
    ),
    (
        'about',
        'About the Project',
        'Why this CMS favors portability and a small footprint.',
        '# About Local CMS\n\nThis scaffold is designed for fast iteration. The backend stays intentionally small while the theme layer remains reusable for a future WordPress conversion.',
        'published',
        CURRENT_TIMESTAMP
    );

INSERT OR IGNORE INTO posts (slug, title, excerpt, body_markdown, status, published_at) VALUES
    (
        'first-launch-note',
        'First Launch Note',
        'What this scaffold gives you on day one.',
        '# First launch note\n\nThe application boots from a single public entry point, creates its SQLite database on demand, and renders starter content through a theme directory that can later map to WordPress templates.',
        'published',
        CURRENT_TIMESTAMP
    ),
    (
        'theme-portability',
        'Theme Portability',
        'Why the theme filenames already look familiar.',
        '# Theme portability\n\nUsing header, footer, index, page, single, and archive templates reduces friction later when exporting the frontend as a WordPress theme.',
        'published',
        datetime(CURRENT_TIMESTAMP, '-1 day')
    );
