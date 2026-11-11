# Products Import

Импорт товаров из `.xlsx` в PostgreSQL: **Slim 4 + Doctrine ORM** на бэкенде, **Angular 19 + NgRx + Angular Material** на фронтенде. Импорт асинхронный: **Symfony Messenger + RabbitMQ**.

Пользователь загружает файл, воркер в фоне разбирает строки, скачивает изображения и делает upsert товаров по `external_code`. Фронтенд показывает прогресс и отчёт по невалидным строкам, список с серверной пагинацией и фильтрами и карточку товара с доп. атрибутами и галереей.

## Быстрый старт

```bash
make up          # создаст .env со случайными паролями, соберёт и поднимет стек
```

| Адрес | Что там |
|---|---|
| http://localhost:8080 | приложение. Логин и пароль заданы в `ADMIN_USERNAME` / `ADMIN_PASSWORD` в `.env`, по умолчанию `admin` / `admin12345` |
| http://localhost:8080/api/docs | Swagger UI, спецификация в [`backend/openapi.yaml`](backend/openapi.yaml) |
| http://localhost:8080/api/health | health check (проверяет БД) |
| http://localhost:15672 | консоль RabbitMQ (доступна только с localhost) |

Пример файла для загрузки лежит в [`backend/tests/Fixtures/import_example.xlsx`](backend/tests/Fixtures/import_example.xlsx). Чтобы заполнить базу демо-данными без импорта: `make fixtures`.

Все команды выводит `make help`.

## Сервисы docker compose

| Сервис | Назначение |
|---|---|
| `app` | PHP-FPM 8.3 с приложением Slim, multi-stage образ: `base` → `vendor` → `prod`, отдельная стадия `dev` для тестов |
| `worker` ×2 | `messenger:consume async`: обработка импорта, перезапуск по `--time-limit` и `--memory-limit` |
| `migrate` | одноразовый контейнер: миграции, объявление очередей, создание учётки. `app` и `worker` стартуют только после его успешного завершения |
| `nginx` | multi-stage: сборка Angular на Node, затем nginx. Отдаёт SPA, проксирует `/api` в FastCGI, раздаёт `/storage/images` |
| `db` | PostgreSQL 16 (плюс БД `products_test` для тестов) |
| `rabbitmq` | брокер. Очереди `imports` и `imports_failed` (DLQ) |

Переменные окружения описаны в [`.env.example`](.env.example).

## Архитектура бэкенда

```
backend/src
├── Domain          сущности и правила, без зависимостей от фреймворка и ORM
├── Application     сценарии (use cases) и порты: интерфейсы для внешнего мира
├── Infrastructure  адаптеры портов: Doctrine, OpenSpout, HttpClient, Messenger, JWT, файловое хранилище
└── Entrypoint      HTTP-контроллеры, middleware и консольные команды: тонкий слой
```

Зависимости направлены строго внутрь: `Entrypoint → Application → Domain`, `Infrastructure` реализует порты из `Application` и `Domain`. Связывание выполняется в [`config/container.php`](backend/config/container.php).

### Как идёт импорт

1. `POST /api/imports`. [`XlsxUploadValidator`](backend/src/Entrypoint/Http/Upload/XlsxUploadValidator.php) проверяет расширение, MIME (по содержимому, а не по заголовку клиента), размер и сигнатуру ZIP. Rate limit задаётся через `IMPORT_RATE_LIMIT` за `IMPORT_RATE_INTERVAL` на пользователя. [`ImportService`](backend/src/Application/Import/ImportService.php) сохраняет файл и создаёт `ImportJob(queued)`, затем ставит в очередь `RunImport`. Ответ: `202 Accepted` с заголовком `Location`.
2. Воркер, [`RunImportHandler`](backend/src/Application/Import/RunImportHandler.php), переводит задачу в `processing`. Потом построчно:
   - [`ProductRowParser`](backend/src/Application/Import/ProductRowParser.php) валидирует строку и собирает все ошибки сразу;
   - [`ProductImporter`](backend/src/Application/Import/ProductImporter.php) сначала скачивает изображения **вне транзакции**, затем в одной короткой транзакции делает upsert товара, атрибутов и изображений.
3. Невалидная строка или недоступная картинка не прерывают импорт: они попадают в отчёт (`issues`) с номером строки, полем и причиной. Прогресс пишется каждые `IMPORT_PROGRESS_EVERY_ROWS` строк.
4. Итог: `completed` или `failed`. После исчерпания повторов Messenger сообщение уходит в DLQ, а [`MarkImportFailedListener`](backend/src/Infrastructure/Messenger/MarkImportFailedListener.php) переводит задачу в `failed`, чтобы фронтенд не опрашивал её вечно.

Переходы статусов явные и проверяются в домене ([`ImportJob`](backend/src/Domain/Import/ImportJob.php)): `queued → processing → completed | failed`, а также `queued → failed`; из финального статуса выйти нельзя. Любой другой переход бросает `InvalidImportTransition`.

Скидка считается по формуле из ТЗ: `(Цена продажи − Закупочная цена) / Цена продажи × 100`. Расчёт ведётся в копейках, без float ([`Money`](backend/src/Domain/Shared/Money.php), [`DiscountCalculator`](backend/src/Domain/Product/DiscountCalculator.php)).

### Что учтено из ревью предыдущего тестового

| Замечание | Как сделано здесь |
|---|---|
| Слои смешаны | `domain / application / infrastructure / entrypoints`; контроллеры только разбирают запрос, вызывают сценарий и отдают ответ |
| Домен зависит от ORM | маппинг Doctrine вынесен в XML ([`Infrastructure/Persistence/Doctrine/Mapping`](backend/src/Infrastructure/Persistence/Doctrine/Mapping)); в сущностях нет ни атрибутов, ни аннотаций ORM |
| Порты не в том слое | интерфейсы `FileStorage`, `ImageDownloader`, `SpreadsheetReader`, `ImportQueue`, `TransactionManager` находятся в `Application`, реализации в `Infrastructure` |
| Внешние вызовы внутри транзакции | HTTP-скачивание изображений выполняется до `transactional()`, постановка в очередь после записи задачи. Транзакция держит соединение миллисекунды |
| Неявные смены статуса | методы `start / complete / fail` с проверкой допустимого перехода, сеттера статуса нет |
| Нет тестов конкурентности | [`ProductImporterTest::testAdvisoryLockSerializesConcurrentUpsertOfSameCode`](backend/tests/Integration/ProductImporterTest.php): два процесса делают upsert одного `external_code`, дубль не появляется (`pg_advisory_xact_lock` плюс уникальный индекс) |
| Доменные тесты требуют БД | `tests/Unit/Domain` работают без БД и контейнера |

Дополнительно:

- **SSRF.** Ссылки на картинки присылает пользователь, поэтому запросы во внутренние сети блокируются (`NoPrivateNetworkHttpClient`).
- **Лимиты.** Размер и время скачивания ограничены, проверяется `Content-Type` изображения.
- **Хранение файлов.** Загруженные `.xlsx` не отдаются наружу, публична только папка `storage/images`.
- **Ошибки API.** Все ответы с ошибкой оформлены по RFC 7807 (`application/problem+json`).

> Если локальный DNS (VPN или прокси с fake-IP 198.18.0.0/15) резолвит внешние хосты в приватные адреса, картинки будут помечены в отчёте как заблокированные. Для локального запуска поставьте `IMAGE_ALLOW_PRIVATE_NETWORK=1`.

### API

| Метод | Путь | Описание |
|---|---|---|
| `POST` | `/api/auth/login` | JWT по логину и паролю |
| `POST` | `/api/imports` | загрузка `.xlsx` (multipart, поле `file`), ответ `202` |
| `GET` | `/api/imports` | последние импорты |
| `GET` | `/api/imports/{id}` | статус, прогресс, счётчики и отчёт об ошибках |
| `GET` | `/api/products?page=1&limit=20&name=&price_min=&price_max=` | список с серверной пагинацией и фильтрами |
| `GET` | `/api/products/{id}` | карточка: поля, атрибуты, изображения |
| `GET` | `/api/health` | health check |

## Фронтенд

```
frontend/src/app
├── models/          интерфейсы Product, ProductAttribute, ProductImage, ImportJob, Page…
├── core/            API_URL, AuthService, guards, interceptors (Bearer, 401/429/5xx), уведомления
├── services/        ProductService, ImportService (upload, polling статуса)
├── store/products/  NgRx: loadProducts / Success / Failure, ProductsEffects, selectAllProducts / selectProductsLoading / selectTotalPages
├── shared/          import-progress: прогресс и отчёт об ошибках
├── layout/          оболочка с навигацией
└── pages/           login, imports, products/list, products/detail
```

- Standalone-компоненты, `strict: true`, `OnPush`, signal inputs. Lazy loading всех страниц, store и effects товаров регистрируются на уровне маршрута.
- Состояние списка хранится в URL (`?page=&name=&price_min=`): ссылкой можно поделиться, кнопка «назад» работает. Компонент читает данные через `store.select()` и `async` pipe, без ручных `subscribe`.
- Прогресс импорта получается polling-ом через `timer + exhaustMap + takeWhile`: следующий запрос уходит только после ответа на предыдущий, опрос прекращается на финальном статусе.
- Защита маршрутов функциональными guards. Interceptor добавляет JWT только к запросам своего API, а при 401 завершает сессию и уводит на логин с `returnUrl`.

## Тесты и качество

| Команда | Что проверяет |
|---|---|
| `make test` | PHPUnit, 86 тестов: unit-тесты домена и сценариев (без БД), интеграционные тесты репозиториев и импорта на PostgreSQL, API e2e (полный цикл через Slim и Messenger) |
| `make phpstan` | PHPStan level 8 по `src`, `config`, `tests` |
| `make lint` | PHP CS Fixer (`@Symfony`, `@Symfony:risky`, `@PHP83Migration`, `strict_types`) |
| `make frontend-test` | Karma и Jasmine: reducer, selectors, effects, сервисы, interceptors, guards, страница списка с MockStore |
| `make e2e` | Playwright по поднятому стеку: вход, загрузка примера, прогресс до «Завершён», фильтр, карточка, SPA fallback |

CI (GitHub Actions, [`.github/workflows/ci.yml`](.github/workflows/ci.yml)) на каждый push выполняет:

- для бэкенда: cs-fixer, PHPStan и PHPUnit с сервисом PostgreSQL;
- для фронтенда: unit-тесты и production-сборку;
- сборку Docker-образов и Playwright e2e по поднятому `docker compose`.
