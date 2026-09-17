# Delete Guard — functional tests

[English](#english) · [Русский](#русский)

---

## English

Functional tests run against a live WordPress install: settings, access policy, trash/delete/restore hooks, and the audit log.

### How to run

From the project root (Docker):

```bash
docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/tests/run.php
```

Or inside the PHP container / host, with WordPress root as cwd:

```bash
php wp-content/plugins/delete-guard/tests/run.php
```

Requirements: running stack, active `delete-guard` plugin, database access.

Exit code: `0` — all passed, `1` — failures.

### Layout

| File | Role |
|------|------|
| `run.php` | CLI entry: loads WP and runs suites |
| `class-test-runner.php` | Executes cases, prints report |
| `class-test-case.php` | Asserts, temp users/pages, settings backup |
| `Functional/` | Scenario suites |

Before each case, plugin settings are backed up; after — restored. Created pages and users are removed.

### What we cover and why

#### Settings save/normalize

Option storage:

- mode and `allow_empty_trash_days` only for existing post types;
- EMPTY flag only with `block_delete`;
- `off` is not stored (default for new types);
- unknown type slugs are dropped.

#### Policy decide matrix

Decision matrix without WP side effects:

- `off` — allowed, no logging;
- `log` — allowed + log;
- `block_trash` — editor cannot trash or permanently delete; admin can;
- `block_delete` — trash OK; permanent delete only for admin or cron when EMPTY is enabled;
- restore always allowed at policy level.

Why: lock the “who / when” contract separately from hooks.

#### Guard trash/delete/restore flows

Core integration (`wp_trash_post`, `wp_delete_post`, `wp_untrash_post`):

- `off` — delete works, no logs;
- `block_trash` — editor denied + `denied` log;
- admin bypasses blocks, log shows `allowed`;
- `block_delete` — editor may trash but not permanently delete;
- restore is logged;
- cron + EMPTY allowed/denied; on deny, `_wp_trash_meta_time` is bumped so `wp_scheduled_delete` does not retry daily.

Why: ensure `pre_*` filters block all delete paths, not only the UI.

#### Logging fields and log-only mode

Log row shape and log-only mode:

- `user_id`, `post_type`, `post_title`, `source`, `created_at`;
- title snapshot remains after hard delete;
- `purge_older_than_days` runs without error.

Why: audit must survive hard delete and feed the Log screen.

### Limits

- Tests use the same DB as the local site — do not run on production.
- Yoast/theme noise when creating posts is possible; it does not affect assertions.
- Multisite: users are created on the current blog; super admin is not covered separately (`administrator` is enough for bypass checks).

---

## Русский

Проверяют поведение плагина на живом WordPress: настройки, политику доступа, хуки trash/delete/restore и запись в лог.

### Запуск

Из корня проекта (Docker):

```bash
docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/tests/run.php
```

Или из контейнера PHP / хоста, если текущая директория — корень WordPress:

```bash
php wp-content/plugins/delete-guard/tests/run.php
```

Нужны: поднятый стек, активный плагин `delete-guard`, доступ к БД сайта.

Код выхода: `0` — все тесты зелёные, `1` — есть ошибки.

### Структура

| Файл | Назначение |
|------|------------|
| `run.php` | Точка входа CLI, подключает WP и запускает наборы |
| `class-test-runner.php` | Прогон кейсов, отчёт в stdout |
| `class-test-case.php` | База: assert’ы, тестовые user/page, бэкап настроек |
| `Functional/` | Наборы сценариев |

Перед каждым кейсом сохраняются текущие настройки плагина, после — восстанавливаются. Созданные страницы и пользователи удаляются.

### Что проверяем и зачем

#### Settings save/normalize

Корректность хранения option:

- режим и флаг `allow_empty_trash_days` пишутся только для существующих post type;
- флаг EMPTY допустим только при `block_delete`;
- режим `off` в option не хранится (дефолт для новых типов);
- неизвестный slug типа отбрасывается.

#### Policy decide matrix

Матрица решений без побочных эффектов WP:

- `off` — действие разрешено, в лог не пишем;
- `log` — разрешено + лог;
- `block_trash` — редактор не может ни в корзину, ни навсегда; admin может;
- `block_delete` — корзина ок, полное удаление только admin или cron при включённом EMPTY;
- restore всегда разрешён на уровне политики.

Зачем: зафиксировать контракт «кто и когда может», отдельно от хуков.

#### Guard trash/delete/restore flows

Интеграция с ядром (`wp_trash_post`, `wp_delete_post`, `wp_untrash_post`):

- при `off` удаление работает, логов нет;
- при `block_trash` у editor отказ + запись `denied`;
- admin при запретах проходит, в логе `allowed`;
- при `block_delete` editor кидает в корзину, но не удаляет навсегда;
- restore пишется в лог;
- cron + EMPTY разрешён/запрещён; при запрете обновляется `_wp_trash_meta_time`, чтобы `wp_scheduled_delete` не долбил пост каждый день.

Зачем: убедиться, что фильтры `pre_*` реально блокируют все пути удаления, а не только UI.

#### Logging fields and log-only mode

Состав строки лога и режим «только логирование»:

- есть user_id, post_type, post_title, source, created_at;
- после полного удаления title в логе остаётся (снимок);
- `purge_older_than_days` отрабатывает без ошибки.

Зачем: аудит должен переживать hard-delete и быть пригоден для экрана «Лог».

### Ограничения

- Тесты ходят в ту же БД, что и локальный сайт: не гонять на проде.
- Шум Yoast/темы при создании постов возможен; на результат assert’ов не влияет.
- Multisite: пользователи создаются на текущем блоге; суперадмин отдельно не покрыт (роли `administrator` достаточно для проверки bypass).
