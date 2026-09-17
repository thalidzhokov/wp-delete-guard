# Build scripts / Скрипты сборки

[English](#english) · [Русский](#русский)

---

## English

### `build-zip.php`

Creates a WordPress.org-ready zip in `../releases/`.

**Included:** plugin PHP, `readme.txt`, `license.txt`, `README.md`, `languages/`, `uninstall.php`  
**Excluded:** `.git`, `tests/`, `bin/`, `releases/`, `.gitignore`, `.distignore`

#### Run

From the plugin root:

```bash
php bin/build-zip.php
```

Optional version override (default: `Version` from `delete-guard.php`):

```bash
php bin/build-zip.php --version=1.0.0
```

Docker (project root):

```bash
docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/bin/build-zip.php
```

#### Output

`releases/delete-guard-{version}.zip`

Inside the archive the root folder is `delete-guard/` (required for install / wordpress.org upload).

Requires PHP with the `ZipArchive` extension.

---

## Русский

### `build-zip.php`

Собирает zip для wordpress.org в `../releases/`.

**В архиве:** PHP плагина, `readme.txt`, `license.txt`, `README.md`, `languages/`, `uninstall.php`  
**Не попадает:** `.git`, `tests/`, `bin/`, `releases/`, `.gitignore`, `.distignore`

#### Запуск

Из корня плагина:

```bash
php bin/build-zip.php
```

Версия вручную (по умолчанию берётся `Version` из `delete-guard.php`):

```bash
php bin/build-zip.php --version=1.0.0
```

Docker (корень проекта):

```bash
docker compose exec -T php php /var/www/html/wp-content/plugins/delete-guard/bin/build-zip.php
```

#### Результат

`releases/delete-guard-{version}.zip`

Внутри архива корень — папка `delete-guard/` (так нужно для установки и загрузки на wordpress.org).

Нужен PHP с расширением `ZipArchive`.
