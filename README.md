# Delete Guard

WordPress plugin: per post type deletion policies — logging, trash blocking, and permanent delete protection.

**Repository:** https://github.com/thalidzhokov/wp-delete-guard  
**Author:** Albert Thalidzhokov  
**Requires:** WordPress 6.0+, PHP 8.0+

[English](#english) · [Русский](#русский)

---

## English

### Features

- Modes per post type: **Off**, **Log only**, **Block trash**, **Block permanent delete**
- Optional `EMPTY_TRASH_DAYS` cron cleanup when permanent delete is blocked
- Audit log: allowed and denied attempts (trash, permanent delete, restore)
- Administrators and network super admins bypass blocks; actions are still logged
- Multisite: settings and log table are per site
- UI: Settings → Delete Guard (Settings + Log tabs)
- Languages: English (default), Russian (`ru_RU`)

### Modes

| Mode | Trash | Permanent delete | Cron `EMPTY_TRASH_DAYS` |
|------|-------|------------------|-------------------------|
| Off | allowed, no log | allowed, no log | allowed, no log |
| Log only | allowed + log | allowed + log | allowed + log |
| Block trash | denied* + log | denied* + log | denied* + log |
| Block permanent delete | allowed + log | denied* + log | optional + log |

\* Administrators / super admins are allowed; the event is logged as allowed.

New post types default to **Off**.

### Installation

1. Copy the plugin to `wp-content/plugins/delete-guard`
2. Activate **Delete Guard**
3. Open **Settings → Delete Guard** and configure modes

Or clone:

```bash
git clone https://github.com/thalidzhokov/wp-delete-guard.git wp-content/plugins/delete-guard
```

### Enforcement

Blocks run through WordPress filters (not UI-only):

- `pre_trash_post`
- `pre_delete_post`
- `pre_delete_attachment`

Successful actions are logged on `trashed_post`, `deleted_post`, `untrashed_post`.

If cron cleanup is denied, `_wp_trash_meta_time` is refreshed so `wp_scheduled_delete` does not retry the same post every day.

### Log

Stored in `{prefix}delete_guard_log`. Fields: status, action, user, source (`admin` / `rest` / `cli` / `cron` / `code`), post snapshot (id, type, title), datetime.

Logs are kept indefinitely. The Log tab has a button to delete entries older than 30 days.

### Tests

See [tests/README.md](tests/README.md).

```bash
php wp-content/plugins/delete-guard/tests/run.php
```

### Release zip

Build a WordPress.org-ready archive (no `.git`, no `tests/`, no `bin/`):

```bash
php bin/build-zip.php
```

Output: `releases/delete-guard-{version}.zip` (folder root inside the zip is `delete-guard/`).

Upload that zip on https://wordpress.org/plugins/developers/add/

### Plugin Check

Scan distributable code (skip local build/test artifacts and the release zip):

```bash
wp plugin check delete-guard --exclude-directories=bin,tests,releases --exclude-files=.gitignore,.distignore
```

### License

GPLv2 or later (WordPress plugin license). See `license.txt`.

---

## Русский

### Возможности

- Режимы по типу поста: **Выключено**, **Только логирование**, **Запрет корзины**, **Запрет полного удаления**
- Опциональная очистка по `EMPTY_TRASH_DAYS` (cron), если запрещено полное удаление
- Лог: одобренные и отклонённые попытки (корзина, удаление навсегда, восстановление)
- Администраторы и суперадмины сети обходят запреты; действия всё равно пишутся в лог
- Multisite: настройки и таблица лога — на каждый сайт
- UI: Настройки → Delete Guard (вкладки «Настройки» и «Лог»)
- Языки: английский (по умолчанию), русский (`ru_RU`)

### Режимы

| Режим | Корзина | Полное удаление | Cron `EMPTY_TRASH_DAYS` |
|-------|---------|-----------------|-------------------------|
| Выключено | да, без лога | да, без лога | да, без лога |
| Только логирование | да + лог | да + лог | да + лог |
| Запрет корзины | отказ* + лог | отказ* + лог | отказ* + лог |
| Запрет полного удаления | да + лог | отказ* + лог | по флагу + лог |

\* Админы / суперадмины могут; в логе статус «одобрено».

Новые типы постов по умолчанию **выключены**.

### Установка

1. Скопировать плагин в `wp-content/plugins/delete-guard`
2. Активировать **Delete Guard**
3. Открыть **Настройки → Delete Guard** и задать режимы

Или клон:

```bash
git clone https://github.com/thalidzhokov/wp-delete-guard.git wp-content/plugins/delete-guard
```

### Как блокируется

Через фильтры ядра (не только скрытие кнопок):

- `pre_trash_post`
- `pre_delete_post`
- `pre_delete_attachment`

Успешные действия пишутся на `trashed_post`, `deleted_post`, `untrashed_post`.

Если cron-очистка запрещена, обновляется `_wp_trash_meta_time`, чтобы `wp_scheduled_delete` не долбил один и тот же пост каждый день.

### Лог

Таблица `{prefix}delete_guard_log`. Поля: статус, действие, пользователь, источник (`admin` / `rest` / `cli` / `cron` / `code`), снимок поста (id, тип, title), дата/время.

Хранение бессрочное. На вкладке «Лог» — кнопка удаления записей старше 30 дней.

### Тесты

См. [tests/README.md](tests/README.md).

```bash
php wp-content/plugins/delete-guard/tests/run.php
```

### Релизный zip

Сборка архива для wordpress.org (без `.git`, без `tests/`, без `bin/`):

```bash
php bin/build-zip.php
```

Результат: `releases/delete-guard-{version}.zip` (внутри корень папки `delete-guard/`).

Этот zip загружают на https://ru.wordpress.org/plugins/developers/add/

### Plugin Check

Проверка дистрибутивного кода (без локальных bin/tests и zip):

```bash
wp plugin check delete-guard --exclude-directories=bin,tests,releases --exclude-files=.gitignore,.distignore
```

### Лицензия

GPLv2 или позднее (как у плагинов WordPress). См. `license.txt`.
