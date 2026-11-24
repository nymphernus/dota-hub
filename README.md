# DotaHub

Статистика игроков **Dota 2** в тёмном адаптивном интерфейсе: поиск по Steam-профилю,
SteamID64, `account_id` или никнейму, профиль с топ-героями, SVG-колесом последних
матчей, активностью за 13 недель, статистикой за всё время и статусом приватности.

Проект собран на Laravel 11, Blade, Tailwind CSS 4 и Alpine.js, разворачивается
одной командой через Docker Compose (PHP-FPM + Nginx + PostgreSQL + Redis).
Интерфейс двуязычный (RU/EN), переключается через `/lang/{locale}`; тема только тёмная.

---

## Возможности

- **Профиль Dota 2** — звание, MMR, винрейт, средние KDA/GPM/XPM и топ-герои.
  Поддерживаются SteamID64, `account_id`, ссылки `/profiles/` и `/id/`.
- **Навигация** — вкладки `Обзор / Матчи / Герои / Союзники / Рекорды` находятся
  только в хедере под логотипом; внутри страницы дублирующих вкладок нет.
- **Обзор** — приватность Steam и Dota, последние матчи, «Самые играемые герои»
  (строго две полосы: винрейт и тонкая жёлтая полоса игр), SVG-колесо героев,
  календарь активности, статистика за всё время, средние показатели и союзники.
- **Календарь активности** — GitHub-style heatmap за 13 недель: серый — нет
  матчей, зелёные оттенки — количество игр. Тултип показывает дату, число игр,
  побед и поражений; подписи дней и месяцев локализованы.
- **Колесо героев** — SVG-секторы по 25 последним матчам с иконкой, именем,
  количеством матчей и винрейтом в тултипе.
- **Последние матчи** — иконка героя, режим, роль, результат W/L, K/D/A, GPM/XPM,
  нетворс и шесть слотов предметов; строка ведёт на страницу матча. Детали
  подгружаются лениво только для 25 новых матчей из кэша.
- **Герои** — отдельная страница с поиском, фильтром по герою, сортировкой
  по играм/винрейту/KDA и пагинацией; страница героя показывает бенчмарки
  и недавние матчи.
- **Матчи** — фильтры по результату, режиму (`Ranked`, `Turbo`, `Normal`), герою
  и роли. Используются официальные ID режимов OpenDota `1,2,4,5,16,18,22,23`.
- **Рекорды и статистика** — группировка по режимам игры и фракциям, средние
  показатели и рекорды считаются локально из закэшированной истории без
  дополнительных запросов к API.
- **Союзники** — список игроков с числом совместных матчей, винрейтом
  и любимыми героями в стиле STRATZ.
- **Steam** — блок приватности в обзоре: SteamID64, PersonaName, аватар, видимость
  сообщества и статус публичности матчей Dota 2. Нужен необязательный
  `STEAM_API_KEY`; без него показываются доступные данные OpenDota и подсказка.
- **Детали матча** — страница `/match/{matchId}` со счётом, ролями, бейджем
  Ranked, режимом, графиками нетворса Radiant/Dire, историей закупа, K/D/A,
  GPM, XPM и уроном. При `STRATZ_API_KEY` или `STRATZ_TOKEN` роли 1–5 и impact
  берутся из STRATZ.
- **Локализация** — RU и EN через `__('ui.*')`; в английском интерфейсе нет
  русских служебных строк, названия режимов на EN остаются английскими.
- **Избранное и аккаунт** — вход через Steam, добавление игроков в избранное,
  до 5 последних поисков на главной (история хранится в PostgreSQL).
- **Кэширование и лимиты** — профиль хранится в PostgreSQL (1 час), детали матчей
  и Steam-сводка — в Redis (30–60 минут), метаданные и агрегаты — сутки; ключи
  статистики включают локаль. Локальный счётчик OpenDota (2000/сутки) и
  HTTP-троттлинг поиска/обновления.
- **Фоновый прогрев** — после поиска Dota-игрока задача в Redis-очереди заранее
  наполняет кэш, чтобы страница открывалась быстрее.
- **Понятные ошибки** — кастомные страницы 404, 429 и 500 в тёмной теме с
  возвратом на главную вместо стандартных страниц Laravel.

---

## Стек

| Слой | Технологии |
| --- | --- |
| Backend | PHP 8.3, Laravel 11 |
| Frontend | Blade, Tailwind CSS 4, Alpine.js 3, Vite 5, SVG/CSS |
| База данных | PostgreSQL 16 |
| Кэш / сессии / очереди | Redis 7 |
| Веб-сервер | Nginx 1.27 + PHP-FPM |
| Контейнеры | Docker, Docker Compose |
| Тесты | PHPUnit, Laravel HTTP fake |

---

## Быстрый старт (Docker)

Требования: Docker Engine 24+ и Docker Compose v2.

```bash
git clone <repo-url> stats-radar
cd stats-radar

cp .env.example .env
# При желании укажите STEAM_API_KEY (блок Steam и ссылки /id/...)
# и OPENDOTA_API_KEY. Пароль PostgreSQL в DB_PASSWORD тоже стоит заменить.

docker compose up -d --build
```

Откройте **http://localhost:8080** (или `http://localhost`, если в `.env` задано
`APP_PORT=80`, как в текущем рабочем окружении).

При первом запуске контейнер `app`:

1. генерирует `APP_KEY` и сохраняет его в volume `app-storage`
   (`storage/app/.app_key`), поэтому ключ не теряется при пересоздании контейнера;
2. ждёт готовности PostgreSQL;
3. выполняет `php artisan migrate --force`;
4. кэширует конфигурацию, маршруты и представления.

Полезные команды:

```bash
docker compose ps                     # статус сервисов
docker compose logs -f app            # логи приложения
docker compose exec app php artisan about
docker compose exec app php artisan cache:clear
docker compose down                   # остановить
docker compose down -v                # остановить и удалить данные (БД, Redis, storage)
```

Порт публикуется через `APP_PORT` (по умолчанию `8080`).

### Проверка после запуска

```bash
curl http://localhost:8080/up          # 200 OK — приложение живо
curl -I http://localhost:8080/         # 200 OK — главная страница
docker compose exec app php artisan migrate:status
```

Разбор Steam-ссылок можно проверить через tinker:

```bash
docker compose exec app php artisan tinker
```

```php
// 1. Парсер возвращает тип и значение, не выполняя сетевых запросов.
App\Support\SteamId::parse('https://steamcommunity.com/id/nymphernus');
// ['type' => 'vanity', 'value' => 'nymphernus']

// 2. Полное разрешение (Steam Web API + вычисление Dota 2 account_id).
app(App\Services\PlayerSearchService::class)
    ->resolve('https://steamcommunity.com/id/nymphernus');
// ['account_id' => 179749744, 'steam_id' => '76561198140015472', ...]
```

Для второго шага нужен `STEAM_API_KEY` и доступ к `api.steampowered.com`.

---

## Переменные окружения

Все настройки находятся в `.env` (шаблон — `.env.example`).

### Приложение

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `APP_ENV` | `production` | Окружение Laravel |
| `APP_DEBUG` | `false` | Не включайте на продакшене |
| `APP_URL` | `http://localhost:8080` | Публичный адрес (в текущем окружении `http://localhost`) |
| `APP_PORT` | `8080` | Порт Nginx на хосте (сейчас `80`) |
| `APP_KEY` | — | Генерируется автоматически, если пуст |
| `APP_LOCALE` | `ru` | Язык по умолчанию |
| `APP_FALLBACK_LOCALE` | `en` | Fallback для переводов |

### База данных и Redis

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `DB_CONNECTION` | `pgsql` | Драйвер БД |
| `DB_HOST` / `DB_PORT` | `db` / `5432` | Адрес PostgreSQL внутри compose-сети |
| `DB_DATABASE` | `stats_radar` | Имя базы |
| `DB_USERNAME` / `DB_PASSWORD` | `stats_radar` / `change-me-in-production` | Учётные данные |
| `CACHE_STORE` | `redis` | Хранилище кэша |
| `SESSION_DRIVER` | `redis` | Хранилище сессий |
| `QUEUE_CONNECTION` | `redis` | Драйвер очередей |
| `REDIS_HOST` / `REDIS_PORT` | `redis` / `6379` | Адрес Redis |

### Внешние API

| Переменная | Обязательна | Назначение |
| --- | --- | --- |
| `STEAM_API_KEY` | Нет | Блок Steam и ссылки `/id/`: https://steamcommunity.com/dev/apikey |
| `STEAM_REDIRECT_URI` | Нет | URL возврата после Steam OAuth (по умолчанию `/auth/steam/callback`) |
| `STEAM_REALM` | Нет | Переопределение `openid.realm` для Steam |
| `STEAM_FORCE_HTTPS` | `false` | Форсировать HTTPS в callback Steam |
| `OPENDOTA_API_KEY` / `OPEN_DOTA_API_KEY` | Нет | Повышает лимит OpenDota; `OPEN_DOTA_API_KEY` — алиас |
| `OPENDOTA_CACHE_TTL` | `3600` | Профиль в PostgreSQL, секунды |
| `OPENDOTA_PLAYER_HERO_CACHE_TTL` | `1800` | Агрегаты по героям игрока, секунды |
| `OPENDOTA_BENCHMARKS_CACHE_TTL` | `86400` | Бенчмарки героев, секунды |
| `OPENDOTA_MATCH_CACHE_TTL` | `3600` | Детали матчей, секунды |
| `OPENDOTA_LIFETIME_STATS_CACHE_TTL` | `86400` | Статистика за всё время (ключ включает локаль) |
| `OPENDOTA_RECORDS_CACHE_TTL` | `86400` | Рекорды, секунды |
| `OPENDOTA_HEROES_CACHE_TTL` | `86400` | Метаданные героев, секунды |
| `OPENDOTA_ITEMS_CACHE_TTL` | `86400` | Метаданные предметов, секунды |
| `OPENDOTA_DAILY_LIMIT` | `2000` | Локальный предохранитель дневного лимита |
| `OPENDOTA_API_IP` | `104.16.0.1` | Docker: IP Cloudflare-края для `api.opendota.com` |
| `FAVORITES_CACHE_TTL` | `2592000` | Кэш избранного, секунды |
| `RECENT_SEARCHES_COUNT` | `5` | Сколько последних поисков показывать на главной |
| `STEAM_CACHE_TTL` | `1800` | Кэш Steam-профиля, секунды |
| `STEAM_VANITY_CACHE_TTL` | `604800` | Кэш резолва `/id/` (7 дней) |
| `STRATZ_API_KEY` / `STRATZ_TOKEN` | Нет | GraphQL STRATZ: позиции 1–5 и impact; `STRATZ_API_KEY` имеет приоритет |
| `STRATZ_ENDPOINT` | `https://api.stratz.com/graphql` | GraphQL endpoint STRATZ |

> Конфигурация кэшируется при старте контейнера. После изменения `.env`
> перезапустите приложение:
> `docker compose up -d --force-recreate app web queue`.

---

## API и лимиты

### OpenDota

- Публичный лимит — около **2000 запросов в сутки** на IP/ключ и 60 запросов в
  минуту на часть эндпоинтов.
- Клиент ведёт счётчик в общем кэше (`opendota:quota:YYYY-MM-DD`) и при
  достижении `OPENDOTA_DAILY_LIMIT` отдаёт понятную ошибку, не отправляя запрос.
- Первый показ профиля запрашивает профиль, win/loss, последние матчи, героев,
  totals и полную историю матчей порциями по 500, а также справочники героев
  и предметов. Детали матчей в таблице обзора лениво догружаются только для
  25 новых записей.
- Полная история нужна для календаря активности (последние 13 недель) и локальных
  агрегатов; «Колесо героев» использует последние 25 матчей. Готовый профиль,
  детали и справочники кэшируются, повторный просмотр лимит не расходует.
- Страница матча дополнительно запрашивает справочник предметов и, если задан
  `STRATZ_API_KEY` или `STRATZ_TOKEN`, один GraphQL-запрос STRATZ.
- Ответы `404`, `429`, `5xx` и сетевые таймауты преобразуются в отдельные
  страницы ошибок, а не в белый экран.
- При сетевом таймауте показывается страница «Не удалось загрузить данные»
  с кнопкой повтора вместо нулевой статистики.
- Если у игрока нет публичных матчей, профиль подсказывает включить
  `Expose Public Match Data` в настройках Dota 2.
- Если OpenDota временно недоступна, уже закэшированные профили продолжают
  открываться.

### STRATZ (опционально)

- `STRATZ_API_KEY` (или алиас `STRATZ_TOKEN`) включает GraphQL-обогащение страницы матча: точные позиции
  1–5 (Safe/Mid/Off/Soft/Hard) и impact вместо оценки `lane_role`/`position_est`
  из OpenDota.
- Без токена сервис не делает запросов, а страница матча полностью работает на
  данных OpenDota.
- Ошибки STRATZ логируются и не ломают страницу: роли откатываются к OpenDota.

#### Если контейнер не подключается к OpenDota

В некоторых сетях DNS отдаёт адреса Cloudflare, которые недоступны из Docker,
хотя сам DNS работает. Compose закрепляет `api.opendota.com` за рабочим
Cloudflare-краем через `extra_hosts`; адрес можно переопределить переменной
`OPENDOTA_API_IP` в `.env`. Проверка:

```bash
docker compose exec app curl -I https://api.opendota.com/api/players/883217516
docker compose up -d --force-recreate app queue   # после изменения OPENDOTA_API_IP
```

Ожидается `HTTP/1.1 200 OK`. Корень `https://api.opendota.com` возвращает `404`
— это нормально, проверяйте именно endpoint API.

### Steam Web API

- Требуется только для блока Steam и резолва ссылок `/id/...`.
- Используются методы `GetPlayerSummaries` (аватар, PersonaName, видимость
  сообщества, время последнего входа) и `ResolveVanityURL`.
- Результат резолва кастомной ссылки кэшируется на 7 дней, краткая сводка
  Steam-профиля — на 30 минут.
- Без `STEAM_API_KEY` профиль продолжает работать: блок Steam показывает
  доступные данные OpenDota и просит добавить ключ.
- Закрытый профиль Steam отражается отдельным статусом приватности; это не
  мешает показывать публичную статистику Dota 2.

---

## Маршруты

| Метод | URI | Назначение |
| --- | --- | --- |
| `GET` | `/` | Главная с поиском и последними запросами |
| `POST` | `/search` | Разбор запроса и редирект на профиль (20 запросов/мин) |
| `GET` | `/lang/{ru\|en}` | Переключение языка интерфейса |
| `GET` | `/auth/steam`, `/auth/steam/callback`, `/auth/steam/logout` | Steam OAuth |
| `GET` | `/favorites` | Список избранных игроков |
| `POST` | `/favorites` | Добавить игрока в избранное |
| `DELETE` | `/favorites/{accountId}` | Удалить игрока из избранного |
| `GET` | `/player/{accountId}` | Обзор профиля Dota 2 |
| `GET` | `/player/{accountId}/matches` | Матчи с фильтрами |
| `GET` | `/player/{accountId}/heroes` | Таблица героев |
| `GET` | `/player/{accountId}/peers` | Союзники |
| `GET` | `/player/{accountId}/records` | Рекорды |
| `GET` | `/player/{accountId}/hero/{heroId}` | Статистика по герою |
| `GET` | `/match/{matchId}` | Детали матча (`?player_id=` подсвечивает игрока) |
| `POST` | `/player/{accountId}/refresh` | Сброс кэша профиля (5 запросов/мин) |
| `GET` | `/up` | Health-check приложения |

Префикса `/dota/` в публичных URL нет: шаблоны лежат в `resources/views/dota/`,
а маршруты и вызовы `view()` используют короткие пути.

---

## Структура проекта

```
app/
  Exceptions/                 # доменные исключения внешних API
  Http/Controllers/           # главная, поиск, профиль, матч, герой, избранное, Steam OAuth
  Jobs/WarmDotaProfile.php    # фоновый прогрев профиля
  Models/                     # PlayerProfile, PlayerSearch, PlayerNameHistory, User
  Services/
    OpenDota/                 # HTTP-клиент, квоты, профиль, матчи, агрегаты
    Steam/                    # Steam Web API и публичная сводка аккаунта
    Stratz/                   # GraphQL: роли и impact (опционально)
    Favorites/                # избранное в Redis
    PlayerSearchService.php   # преобразование ввода в Dota-игрока
  Support/SteamId.php         # SteamID64, account_id, ссылки Steam
docker/                       # Dockerfile, entrypoint, php.ini, nginx
lang/{ru,en}/ui.php           # вся текстовая локализация интерфейса
resources/
  css/app.css                 # тёмная тема Tailwind и компоненты
  js/app.js                   # Alpine.js: избранное и интерактивные элементы
  js/heatmap.js               # календарь активности и SVG-колесо
  views/layouts/app.blade.php # хедер с вкладками, язык, поиск
  views/dota/layouts/         # каркас профиля без собственных вкладок
  views/dota/                 # player, matches, heroes, peers, records, hero, match
  views/dota/partials/        # hero-wheel, match-row, records-grid и др.
  views/components/           # player-header, player-metrics и др.
routes/web.php
tests/Feature/, tests/Unit/
```

Вкладки профиля объявлены один раз в `resources/views/layouts/app.blade.php`;
`resources/views/dota/layouts/profile.blade.php` только подключает контент страницы.

### Как обрабатывается запрос

1. `SearchController` валидирует ввод и передаёт его в `PlayerSearchService`.
2. `PlayerSearchService` разбирает строку через `SteamId`: ссылка `/profiles/` →
   SteamID64, `account_id` → SteamID64, `/id/` → `ResolveVanityURL` к Steam Web API
   (нужен `STEAM_API_KEY`), никнейм → поиск OpenDota. SteamID64 конвертируется
   в `account_id` вычитанием базовой константы.
3. Ставится задача `WarmDotaProfile` и выполняется редирект на
   `/player/{accountId}`.
4. Контроллер профиля читает сохранённый профиль из PostgreSQL, записывает поиск
   в `player_searches`, лениво догружает детали 25 последних матчей и локально
   считает колесо героев, heatmap за 13 недель, статистику за всё время и рекорды.
   Затем получает публичную сводку Steam и рендерит Blade.
5. Вкладки profile-страниц заданы только в `layouts/app.blade.php`; layout профиля
   подключает контент без собственного `role="tablist"`.
6. Кнопка «Обновить» сбрасывает кэш одного профиля и Steam-сводки и
   перезагружает страницу.

---

## Локальная разработка (без Docker)

Требования: PHP 8.3+ с расширениями `pdo_sqlite`, `mbstring`, `intl`, `zip`,
Composer 2, Node.js 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate

# Для локального запуска проще SQLite и файловые кэш/сессии:
# DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database/database.sqlite
# CACHE_STORE=file
# SESSION_DRIVER=file
# QUEUE_CONNECTION=sync

touch database/database.sqlite   # Windows PowerShell: New-Item database/database.sqlite
php artisan migrate

npm install
npm run dev        # отдельный терминал
php artisan serve
```

Для production-сборки фронтенда: `npm run build`. Каталог `public/build`
игнорируется git и собирается внутри Docker-образа.

---

## Тесты

Локально (нужны PHP 8.3+ и dev-зависимости Composer):

```bash
composer test
```

В Docker — отдельный образ `test` с dev-зависимостями; production-образ остаётся
без PHPUnit и Collision:

```bash
docker compose --profile testing run --rm test
```

Первый запуск соберёт образ `stats-radar/test:local` (несколько минут), повторные
запуски используют кэш. Тесты монтируются из рабочего каталога, поэтому правки
не требуют пересборки.

136 тестов и 1092 проверки покрывают:

- разбор SteamID64, `account_id`, ссылок `/profiles/` и `/id/`, никнеймов;
- поиск, редиректы, фоновую задачу, ошибки валидации и Steam OAuth;
- структуру навигации: вкладки только в хедере, без дубля внутри контента;
- рендеринг обзора: приватность, последние матчи, строгое колесо героев
  (две полосы, без синей), heatmap, статистику по режимам/фракциям и союзников;
- матчи и фильтры, официальные режимы и локализацию RU/EN (включая отсутствие
  кириллицы в английском интерфейсе);
- страницы героев, рекордов, деталей матча, избранное и кастомные ошибки;
- кэш, ленивую загрузку деталей, STRATZ-фолбэк и защиту лимитов.

Тесты используют SQLite `:memory:`, `CACHE_STORE=array` и `Http::fake()`, поэтому
не обращаются к реальным API.

> В `phpunit.xml` включён `restrictDeprecations="true"`: на локальном PHP 8.5
> Laravel 11 генерирует deprecation-предупреждения из vendor-конфига
> (`PDO::MYSQL_ATTR_SSL_CA`). Целевая версия в Docker — PHP 8.3, где этих
> предупреждений нет.

---

## Production-заметки

- Поставьте `APP_DEBUG=false`, смените `DB_PASSWORD` и включите HTTPS
  (reverse proxy или балансировщик перед контейнером `web`).
- Не публикуйте PostgreSQL и Redis наружу: в compose у них нет проброса портов,
  и это правильно.
- Данные лежат в named volumes: `db-data`, `redis-data`, `app-storage`.
  Настройте их резервное копирование (`pg_dump` для PostgreSQL).
- `docker compose exec app php artisan config:cache route:cache view:cache`
  выполняется автоматически при старте; после правок `.env` пересоздайте
  контейнеры.
- Для горизонтального масштабирования запускайте несколько `queue`-контейнеров;
  задачи идемпотентны, а `WarmDotaProfile` уникален в течение 10 минут.
- Логи: `storage/logs/laravel.log` внутри volume `app-storage` и
  `docker compose logs`.
- Health-check: `GET /up` (используется в Docker healthcheck Nginx).

---

## Устранение неполадок

- **Сборка идёт долго.** Первый `docker compose build` скачивает базовые образы и
  собирает PHP-расширения (`intl`, `pdo_pgsql`, `redis`) — на медленной сети это
  может занять 10–20 минут. Слои кэшируются, повторные сборки заметно быстрее.
  В `Dockerfile` для `npm ci` увеличены таймауты и число повторов.
- **Контейнер `app` перезапускается.** Посмотрите `docker compose logs app`.
  Entrypoint ждёт PostgreSQL через `pg_isready` до 60 секунд и завершает работу,
  если БД недоступна. Проверьте `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`,
  `DB_PASSWORD` и здоровье сервиса `db`.
- **Блок Steam без данных.** Не задан `STEAM_API_KEY` или Steam недоступен.
  Добавьте ключ в `.env` и пересоздайте контейнеры:
  `docker compose up -d --force-recreate app web queue`.
- **Ошибка OpenDota 502/429.** Проверьте доступ к `api.opendota.com` и дневной
  лимит. Ранее закэшированные профили продолжают открываться.
- **Изменения `.env` не применяются.** При старте выполняется `config:cache`;
  пересоздайте контейнеры, а не только перезапустите их.
- **Права на storage.** Используйте `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`
  или пересоздайте volume `app-storage`.

---

## Ограничения и известные особенности

- **Laravel 11 достиг End of Life.** На момент сборки Composer блокировал
  установку из-за security advisories, поэтому в `composer.json` отключён
  `policy.advisories.block`. Для долгой эксплуатации обновитесь до актуальной
  LTS-версии Laravel; код сервисов и контроллеров к этому готов.
- OpenDota не гарантирует полноту приватных профилей: часть данных может быть
  скрыта, страница покажет то, что доступно публично.
- Никнейм в Dota ищется через OpenDota и может привести к одноимённому игроку —
  надёжнее использовать SteamID64 или ссылку на профиль.
- Для ссылок `steamcommunity.com/id/{name}` требуется `STEAM_API_KEY`: Steam
  резолвит vanity-имена только через авторизованный Web API.
- Steam Web API не отдаёт рейтинг или K/D игрока: блок показывает только
  публичные данные аккаунта и статус приватности.
- Если у игрока выключен `Expose Public Match Data`, матчи и графики будут
  пустыми до включения настройки в Dota 2.

---

## Лицензия

MIT. Данные принадлежат Valve/OpenDota и соответствующим правообладателям.
Проект не аффилирован с Valve, Dotabuff или Stratz.
