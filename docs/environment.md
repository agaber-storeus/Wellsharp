# Environment Configuration

The application reads configuration through Laravel's `.env` file. `.env.example` is the source of the development defaults. Do not commit `.env` or any production secret.

## Application

| Variable | Required | Default | Description |
| --- | ---: | --- | --- |
| `APP_NAME` | No | `WellSharp` | Application display name. |
| `APP_ENV` | Yes | `local` | Environment name. Demo seeding is allowed only in `local` and `testing`. |
| `APP_KEY` | Yes | — | Laravel encryption key; generate with `php artisan key:generate`. |
| `APP_DEBUG` | No | `false` | Debug output switch. Keep `false` in production. |
| `APP_URL` | Yes | `http://localhost` | Base URL used for generated URLs and public storage URLs. |
| `APP_LOCALE` | No | `en` | Application locale. |
| `APP_FALLBACK_LOCALE` | No | `en` | Fallback locale. |
| `APP_FAKER_LOCALE` | No | `en_US` | Faker locale for factories/tests. |

## Database

| Variable | Required | Default | Description |
| --- | ---: | --- | --- |
| `DB_CONNECTION` | Yes | `sqlite` in Laravel config | Production target is MySQL 8+; the test suite uses SQLite. |
| `DB_HOST` | MySQL only | `127.0.0.1` | Database host. |
| `DB_PORT` | No | `3306` for MySQL | Database port. |
| `DB_DATABASE` | Yes | `laravel` per Laravel config | Database name or SQLite path. |
| `DB_USERNAME` | MySQL only | `root` | Database user. |
| `DB_PASSWORD` | MySQL only | empty | Database password. Never commit production values. |
| `DB_SOCKET` | No | empty | Optional MySQL/MariaDB socket. |
| `DB_CHARSET` | No | `utf8mb4` | MySQL/MariaDB character set. |
| `DB_COLLATION` | No | `utf8mb4_unicode_ci` | MySQL/MariaDB collation. |
| `MYSQL_ATTR_SSL_CA` | No | empty | Optional MySQL/MariaDB CA path. The project uses `Pdo\\Mysql::ATTR_SSL_CA` on PHP versions that provide it and falls back to the legacy constant when necessary. |

## Session, cache, queue, and mail

| Variable | Required | Default | Description |
| --- | ---: | --- | --- |
| `SESSION_DRIVER` | No | `database` | Session persistence driver. The `sessions` table is required for the database driver. |
| `SESSION_LIFETIME` | No | `120` | Session lifetime in minutes. |
| `SESSION_ENCRYPT` | No | `false` | Encrypt session values. |
| `SESSION_TABLE` | No | `sessions` | Database session table. |
| `SESSION_SECURE_COOKIE` | Production | `false` | Set `true` when serving over HTTPS. |
| `SESSION_DOMAIN` | No | null | Session cookie domain. |
| `CACHE_STORE` | No | `database` | Cache store. |
| `QUEUE_CONNECTION` | No | `database` | Queue connection. No domain-specific queued jobs currently exist. |
| `DB_QUEUE_TABLE` | No | `jobs` | Database queue table. |
| `DB_QUEUE_RETRY_AFTER` | No | `90` | Database queue retry interval. |
| `MAIL_MAILER` | No | `log` | Mail transport. No mail notification workflow is implemented. |
| `MAIL_HOST` | No | `127.0.0.1` | Mail host. |
| `MAIL_PORT` | No | `2525` | Mail port. |
| `MAIL_USERNAME` | No | null | Mail username. |
| `MAIL_PASSWORD` | No | null | Mail password. Never commit production values. |
| `MAIL_FROM_ADDRESS` | No | `hello@example.com` | Sender address. |
| `MAIL_FROM_NAME` | No | `${APP_NAME}` | Sender name. |

## Files and optional cloud storage

| Variable | Required | Default | Description |
| --- | ---: | --- | --- |
| `FILESYSTEM_DISK` | No | `local` | Default filesystem disk. Question/profile uploads use the configured public disk where their services specify it. |
| `AWS_ACCESS_KEY_ID` | S3 only | empty | S3 access key. Sensitive; never commit. |
| `AWS_SECRET_ACCESS_KEY` | S3 only | empty | S3 secret. Sensitive; never commit. |
| `AWS_DEFAULT_REGION` | S3 only | `us-east-1` | S3 region. |
| `AWS_BUCKET` | S3 only | empty | S3 bucket. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | No | `false` | S3 path-style option. |

## Redis and optional queue backends

`REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`, `REDIS_URL`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_QUEUE_CONNECTION`, and `REDIS_QUEUE` are Laravel configuration options. Redis is not required by the current synchronous domain workflows. Be careful not to expose passwords in logs or version control.

`BEANSTALKD_QUEUE_HOST`, `BEANSTALKD_QUEUE`, `BEANSTALKD_QUEUE_RETRY_AFTER`, `SQS_PREFIX`, `SQS_QUEUE`, `SQS_SUFFIX`, and `AWS_*` support Laravel's optional queue drivers; none is required by the implemented feature.

## Setup

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

For local testing data:

```powershell
php artisan wellsharp:seed-demo
```

For automatic Class transitions, configure the Laravel scheduler to run every minute. The application registers `wellsharp:process-exam-schedules` in `routes/console.php` with `->everyMinute()->withoutOverlapping()`; that registration only tells Laravel *when* to run the command, so something must still invoke the scheduler continuously:

- Local development: run `php artisan schedule:work` in a dedicated terminal (already included in `composer run dev`).
- Production: invoke `php artisan schedule:run` once a minute via the host's process manager — a cron entry (`* * * * * cd /path/to/wellsharp && php artisan schedule:run >> /dev/null 2>&1`) on a traditional server, or the platform's native scheduler integration if Supervisor, systemd, or a PaaS is used instead. See the [README's Scheduled tasks section](../README.md#scheduled-tasks-laravel-scheduler) for details. Never run more than one scheduler-invoking process per environment.
