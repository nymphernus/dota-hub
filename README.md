# DotaHub

Статистика игроков **Dota 2** в тёмном адаптивном интерфейсе: поиск по Steam-профилю,
SteamID64, `account_id` или никнейму, профиль с графиками, активности за полгода,
последними матчами и публичным статусом Steam.

Проект собран на Laravel 11, Blade, Tailwind CSS 4 и Alpine.js, разворачивается
одной командой через Docker Compose (PHP-FPM + Nginx + PostgreSQL + Redis).

---

## Возможности

- **Профиль Dota 2** — звание, MMR, винрейт, средние KDA/GPM/XPM, топ героев.
  Поддерживаются SteamID64, `account_id`, ссылки `/profiles/` и `/id/`.
- **Обзор** — компактная сводка: винрейт, календарь активности за 6 месяцев,
  использование героев за последние 25 матчей (топ-5 и «Другие»), любимые герои
  и последние матчи с деталями.
- **Календарь активности** — GitHub-style heatmap: серый — нет матчей,
  светло-зелёный — 1–2 игры, зелёный — 3–4, тёмно-зелёный — 5+. При наведении
  видно дату, количество игр, побед и поражений.
- **Любимые герои** — компактная таблица: иконка, имя, число игр и винрейт.
- **Последние матчи** — иконка героя, уровень, результат W/L, K/D/A, GPM/XPM,
  нетворс и шесть слотов предметов. Строка ведёт на страницу матча.
- **Герои** — отдельная вкладка с поиском, сортировкой и KDA по героям.
- **Steam** — SteamID64, PersonaName, аватар, последний вход в Dota 2,
  видимость Steam-сообщества и статус публичности матчей Dota 2. Для Steam-данных
  нужен необязательный `STEAM_API_KEY`; без него вкладка показывает доступные
  данные OpenDota и понятное предупреждение.
- **Детали матча** — страница `/dota/match/{id}` со счётом, ролями, бейджем
  Ranked, режимом игры, графиками нетворса Radiant и Dire, историей закупа,
  K/D/A, GPM, XPM и уроном. При наличии `STRATZ_TOKEN` роли 1–5 и impact
  берутся из STRATZ, иначе используется оценка OpenDota.
- **Последние поиски** — до 5 уникальных игроков на главной с аватаром, ником
  и ссылкой на профиль; история хранится в PostgreSQL.
- **Кэширование** — профили живут в Redis 30 минут, детали матчей — час,
  метаданные героев и предметов — сутки. Повторный просмотр не тратит лимит API.
- **Кнопка обновления** — сбрасывает кэш только одного игрока и перезагружает
  страницу со свежими данными.
- **Защита лимитов** — локальный счётчик запросов OpenDota (2000/сутки по
  умолчанию) и HTTP-троттлинг поиска/обновления.
- **Фоновый прогрев** — после поиска Dota-игрока задача в Redis-очереди заранее
  наполняет кэш, чтобы страница открывалась быстрее.
- **Понятные ошибки** — кастомные страницы 404, 429 и 500 в тёмной теме с
  возвратом на главную вместо стандартных страниц Laravel.

---

## Стек

| Слой | Технологии |
| --- | --- |
| Backend | PHP 8.3, Laravel 11 |
| Frontend | Blade, Tailwind CSS 4, Alpine.js 3, Vite 5, Chart.js |
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
# При желании укажите STEAM_API_KEY (Steam-вкладка и ссылки /id/...)
# и OPENDOTA_API_KEY. Пароль PostgreSQL в DB_PASSWORD тоже стоит заменить.

docker compose up -d --build
```

Откройте **http://localhost:8080**.

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
| `APP_URL` | `http://localhost:8080` | Публичный адрес |
| `APP_PORT` | `8080` | Порт Nginx на хосте |
| `APP_KEY` | — | Генерируется автоматически, если пуст |
| `APP_LOCALE` | `ru` | Язык интерфейса |

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
| `STEAM_API_KEY` | Нет | Steam-вкладка и ссылки `/id/`: https://steamcommunity.com/dev/apikey |
| `OPENDOTA_API_KEY` | Нет | Повышает лимит OpenDota и добавляется в запросы |
| `OPENDOTA_CACHE_TTL` | `1800` | Время жизни кэша Dota-профиля, секунды |
| `OPENDOTA_MATCH_CACHE_TTL` | `3600` | Кэш деталей матча и последних матчей, секунды |
| `OPENDOTA_HEROES_CACHE_TTL` | `86400` | Кэш метаданных героев, секунды |
| `OPENDOTA_ITEMS_CACHE_TTL` | `86400` | Кэш метаданных предметов, секунды |
| `OPENDOTA_DAILY_LIMIT` | `2000` | Локальный предохранитель дневного лимита |
| `OPENDOTA_API_IP` | `104.16.0.1` | Docker: IP Cloudflare-края для `api.opendota.com`, если локальный DNS возвращает недоступные адреса |
| `RECENT_SEARCHES_COUNT` | `5` | Сколько последних поисков показывать на главной |
| `STEAM_CACHE_TTL` | `1800` | Время жизни кэша Steam-профиля, секунды |
| `STEAM_VANITY_CACHE_TTL` | `604800` | Кэш резолва кастомных Steam-ссылок (7 дней) |
| `STRATZ_TOKEN` | Нет | Токен STRATZ GraphQL: точные позиции 1–5 и impact на странице матча |
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
- Первый показ профиля делает до 6 запросов игрока (профиль, win/loss, последние
  матчи, герои, totals, история за 182 дня), запрашивает справочники героев и
  предметов и до 10 компактных деталей последних матчей. Детали, справочники и
  готовый профиль кэшируются, поэтому повторный просмотр лимит не расходует.
- История матчей загружается с `limit=500&date=182` для календаря активности;
  последние 25 матчей используются для графика героев.
- Страница матча дополнительно запрашивает справочник предметов и, если задан
  `STRATZ_TOKEN`, один GraphQL-запрос STRATZ.
- Ответы `404`, `429`, `5xx` и сетевые таймауты преобразуются в отдельные
  страницы ошибок, а не в белый экран.
- При сетевом таймауте показывается страница «Не удалось загрузить данные»
  с кнопкой повтора вместо нулевой статистики.
- Если у игрока нет публичных матчей, профиль подсказывает включить
  `Expose Public Match Data` в настройках Dota 2.
- Если OpenDota временно недоступна, уже закэшированные профили продолжают
  открываться.

### STRATZ (опционально)

- `STRATZ_TOKEN` включает GraphQL-обогащение страницы матча: точные позиции
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

- Требуется только для вкладки Steam и резолва ссылок `/id/...`.
- Используются методы `GetPlayerSummaries` (аватар, PersonaName, видимость
  сообщества, время последнего входа) и `ResolveVanityURL`.
- Результат резолва кастомной ссылки кэшируется на 7 дней, краткая сводка
  Steam-профиля — на 30 минут.
- Без `STEAM_API_KEY` профиль продолжает работать: Steam-вкладка показывает
  доступные данные OpenDota и просит добавить ключ.
- Закрытый профиль Steam отражается отдельным статусом приватности; это не
  мешает показывать публичную статистику Dota 2.

---

## Маршруты

| Метод | URI | Назначение |
| --- | --- | --- |
| `GET` | `/` | Главная с поиском и последними запросами |
| `POST` | `/search` | Разбор запроса и редирект на профиль (20 запросов/мин) |
| `GET` | `/dota/player/{accountId}` | Профиль Dota 2 |
| `GET` | `/dota/match/{matchId}` | Детальная статистика матча (`?player_id=` подсвечивает игрока) |
| `POST` | `/dota/player/{accountId}/refresh` | Сброс кэша Dota-профиля (5 запросов/мин) |
| `GET` | `/up` | Health-check приложения |

---

## Структура проекта

```
app/
  Exceptions/                 # доменные исключения внешних API
  Http/Controllers/           # главная, поиск, профиль, обновление кэша
  Jobs/WarmDotaProfile.php    # фоновый прогрев Dota-профиля
  Models/PlayerSearch.php     # история поиска для главной
  Services/
    OpenDota/                 # HTTP-клиент, квоты, профиль и матчи
    Steam/                    # Steam Web API: vanity и публичный статус
    PlayerSearchService.php   # преобразование ввода в Dota-игрока
  Support/SteamId.php         # SteamID64, account_id, ссылки Steam
docker/                       # Dockerfile, entrypoint, php.ini, nginx
resources/
  css/app.css                 # тема Tailwind и компоненты
  js/app.js                   # точка входа Alpine.js
  js/heatmap.js               # календарь активности на нативном Date
  views/dota/partials/         # SVG-колесо героев и строки матчей
  views/                      # layout, home, dota, partials, errors
routes/web.php
tests/Feature/                # поиск, профиль Dota, матчи, главная, ошибки
tests/Unit/SteamIdTest.php
```

### Как обрабатывается запрос

1. `SearchController` валидирует ввод и передаёт его в `PlayerSearchService`.
2. `PlayerSearchService` разбирает строку через `SteamId`:
   ссылка `/profiles/` → SteamID64, `account_id` → SteamID64, `/id/` → запрос
   `ResolveVanityURL` к Steam Web API (нужен `STEAM_API_KEY`), никнейм → поиск
   OpenDota. SteamID64 для Dota конвертируется в `account_id` вычитанием
   базовой константы.
3. Ставится задача `WarmDotaProfile` и выполняется редирект на
   `/dota/player/{accountId}`.
4. Контроллер профиля берёт агрегированные данные из `Cache::remember`,
   записывает успешный поиск в `player_searches`, собирает календарь активности
   и график героев, получает публичную сводку Steam и рендерит Blade-шаблон.
5. Кнопка «Обновить» вызывает `forget()` для профиля и Steam-сводки и
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

46 тестов и 182 проверки покрывают:

- разбор SteamID64, `account_id`, ссылок `/profiles/` и `/id/`, никнеймов;
- поиск и редиректы, фоновую задачу, ошибки валидации;
- резолв кастомных Steam-ссылок через `ResolveVanityURL`, его кэш и ошибку при
  отсутствии ключа;
- рендеринг Dota-профиля, графики, календарь активности, использование героев,
  Steam-вкладку, кэш, принудительное обновление, 404/502;
- детали последних матчей: уровень, нетворс, предметы и кэш;
- детали матча: счёт, составы, подсветку игрока, кэш на час и 404/пустой ответ;
- главную страницу, лимит и дедупликацию последних поисков.

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
- **Steam-вкладка без данных.** Не задан `STEAM_API_KEY` или Steam недоступен.
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
- Steam Web API не отдаёт рейтинг или K/D игрока: вкладка показывает только
  публичные данные аккаунта и статус приватности.
- Если у игрока выключен `Expose Public Match Data`, матчи и графики будут
  пустыми до включения настройки в Dota 2.

---

## Лицензия

MIT. Данные принадлежат Valve/OpenDota и соответствующим правообладателям.
Проект не аффилирован с Valve, Dotabuff или Stratz.
