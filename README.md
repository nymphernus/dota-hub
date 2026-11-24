# DOTAHUB

Статистика игроков **Dota 2**: поиск по Steam-профилю,
SteamID64, `account_id` или никнейму.

<img width="1239" height="907" alt="dotahub" src="https://github.com/user-attachments/assets/a0ac98e4-32cf-4253-9a64-b11721dd9e86" />

---

## Возможности

- **Профиль Dota 2** — звание, MMR, винрейт, средние KDA/GPM/XPM и топ-герои.
  Поддерживаются SteamID64, `account_id`, ссылки `/profiles/` и `/id/`.
- **Обзор** — последние матчи, «Самые играемые герои», колесо героев,
  календарь активности, статистика за всё время, средние показатели и союзники.
- **Календарь активности** — Отображение активности за 3 месяца. Тултип показывает дату, число игр,
  побед и поражений.
- **Колесо героев** — секторы по 25 последним матчам с иконкой, именем,
  количеством матчей и винрейтом в тултипе.
- **Последние матчи** — герой, режим, роль, результат W/L, K/D/A, GPM/XPM,
  нетворс и шесть слотов предметов; строка ведёт на страницу матча.
- **Герои** — отдельная страница с поиском, фильтром по герою, сортировкой
  по играм/винрейту/KDA и пагинацией.
- **Матчи** — фильтры по результату, режиму, герою
  и роли.
- **Рекорды** — Рекорды считаются локально из закэшированной истории без
  дополнительных запросов к API.
- **Союзники** — список игроков с числом совместных матчей, винрейтом
  и любимыми героями.
- **Детали матча** — страница со счётом, ролями, режимом, K/D/A.
- **Локализация** — RU и EN.
- **Избранное и аккаунт** — вход через Steam, добавление игроков в избранное.

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
git clone https://github.com/nymphernus/dota-hub.git
cd dota-hub

cp .env.example .env
# При желании укажите STEAM_API_KEY (блок Steam и ссылки /id/...)
# и OPENDOTA_API_KEY. Пароль PostgreSQL в DB_PASSWORD тоже стоит заменить.

docker compose up -d --build
```

Откройте **http://localhost:8080** (или `http://localhost`, если в `.env` задано
`APP_PORT=80`).

При первом запуске контейнер `app`:

1. генерирует `APP_KEY` и сохраняет его в volume `app-storage`
   (`storage/app/.app_key`), поэтому ключ не теряется при пересоздании контейнера;
2. ждёт готовности PostgreSQL;
3. выполняет `php artisan migrate --force`;
4. кэширует конфигурацию, маршруты и представления.


Порт публикуется через `APP_PORT` (по умолчанию `8080`).

### Проверка после запуска

```bash
curl http://localhost:8080/up          # 200 OK — приложение живо
curl -I http://localhost:8080/         # 200 OK — главная страница
docker compose exec app php artisan migrate:status
```

## Переменные окружения

Все настройки находятся в `.env` (шаблон — `.env.example`).

### Приложение

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `APP_PORT` | `8080` | Порт Nginx на хосте (сейчас `80`) |
| `DB_HOST` / `DB_PORT` | `db` / `5432` | Адрес PostgreSQL внутри compose-сети |
| `DB_DATABASE` | `dota_hub` | Имя базы |
| `DB_USERNAME` / `DB_PASSWORD` | `dota_hub` / `1234` | Учётные данные |
| `REDIS_HOST` / `REDIS_PORT` | `redis` / `6379` | Адрес Redis |
| `STEAM_API_KEY` | Нет | Блок Steam и ссылки `/id/`: https://steamcommunity.com/dev/apikey |
| `STEAM_REDIRECT_URI` | Нет | URL возврата после Steam OAuth (по умолчанию `/auth/steam/callback`) |
| `STEAM_REALM` | Нет | Переопределение `openid.realm` для Steam |
| `STEAM_FORCE_HTTPS` | `false` | Форсировать HTTPS в callback Steam |
| `OPENDOTA_API_KEY` / `OPEN_DOTA_API_KEY` | Нет | Повышает лимит OpenDota |
| `OPENDOTA_CACHE_TTL` | `3600` | Профиль в PostgreSQL, секунды |
| `OPENDOTA_PLAYER_HERO_CACHE_TTL` | `1800` | Агрегаты по героям игрока, секунды |
| `OPENDOTA_BENCHMARKS_CACHE_TTL` | `86400` | Бенчмарки героев, секунды |
| `OPENDOTA_MATCH_CACHE_TTL` | `3600` | Детали матчей, секунды |
| `OPENDOTA_LIFETIME_STATS_CACHE_TTL` | `86400` | Статистика за всё время |
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

---

## API и лимиты

### OpenDota

- Публичный лимит — около **2000 запросов в сутки** на IP/ключ и 60 запросов в
  минуту на часть эндпоинтов.
- Клиент ведёт счётчик в общем кэше (`opendota:quota:YYYY-MM-DD`) и при
  достижении `OPENDOTA_DAILY_LIMIT` отдаёт понятную ошибку, не отправляя запрос.
- Первый показ профиля запрашивает профиль, win/loss, последние матчи, героев, полную историю матчей порциями по 500, а также справочники героев
  и предметов. Детали матчей в таблице обзора лениво догружаются только для
  25 новых записей.
- Полная история нужна для календаря активности и локальных
  агрегатов; «Колесо героев» использует последние 25 матчей. Готовый профиль,
  детали и справочники кэшируются, повторный просмотр лимит не расходует.
- Страница матча дополнительно запрашивает справочник предметов и, если задан
  `STRATZ_API_KEY` или `STRATZ_TOKEN`, один GraphQL-запрос STRATZ.
- Если OpenDota временно недоступна, уже закэшированные профили продолжают
  открываться.

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
