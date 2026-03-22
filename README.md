# Telegram Friends Bot

[![CI](https://github.com/gigoshkin/telegram-friends-bot/actions/workflows/deploy.yml/badge.svg)](https://github.com/gigoshkin/telegram-friends-bot/actions/workflows/deploy.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white)](https://symfony.com)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A self-hosted Telegram bot that lets you create AI clones of your friends. Train each clone on exported chat history, add it to a group, and watch it imitate whoever you want — complete with their vocabulary, phrasing, and response patterns.

---

## How It Works

1. You run the main bot on your own server
2. A friend sends `/start` and creates a **clone bot** via @BotFather
3. They export their Telegram group chat history and upload the `result.json` file
4. They pick **who** the clone should imitate from the participant list
5. The clone registers a webhook automatically and starts replying in group chats

When someone messages the clone bot in a group, it finds the most similar message the target person ever replied to (using PostgreSQL trigram similarity + optional full-text search), then responds with what they actually said.

---

## Features

- **Multiple bots per user** — one account, many clones
- **Reusable chat exports** — train several clones from the same group export (different targets)
- **Configurable response behavior** — tune similarity thresholds, response probability, hybrid scoring, and more per bot
- **Large file support** — files >20 MB upload via a generated web link instead of Telegram
- **Debug mode** — shows match scores and trigger text inline for tuning
- **Async import** — chat history is imported in the background, no timeouts on large files
- **Self-contained deployment** — single Docker container with PostgreSQL, Redis, and the worker all inside

---

## Demo Flow

```
You → /start → Main Bot
Main Bot → "Let's set up your clone! Create a bot via @BotFather..."
You → paste bot token
Main Bot → "Send me the result.json from Telegram Desktop"
You → upload result.json
Main Bot → shows participant list:
  ┌─────────────────────────────┐
  │  John Doe    · 1,234 msgs   │
  │  Alice Smith ·   956 msgs   │
  │  Bob Johnson ·   812 msgs   │
  └─────────────────────────────┘
You → tap "John Doe"
Main Bot → "✅ Done! Your bot is now imitating John Doe. Add it to a group and watch it go! 🎭"
```

---

## Self-Hosting

### Prerequisites

- A Linux VPS with [CapRover](https://caprover.com) installed
- A domain pointed at the VPS
- A Telegram bot token for the **main bot** (from [@BotFather](https://t.me/botfather))

### Deploy to CapRover

**1. Create the app**

In your CapRover dashboard:
- Create a new app, e.g. `friends-bot`
- Enable **"Has Persistent Data"**
- Add the following persistent directories:

| Container path | Purpose |
|---|---|
| `/var/lib/postgresql/data` | PostgreSQL database files |
| `/var/lib/redis` | Redis AOF persistence |
| `/data` | Caddy state |
| `/chat_exports` | Uploaded chat history files |

**2. Set environment variables**

Under App Config → Environmental Variables:

```env
APP_ENV=prod
APP_SECRET=                        # openssl rand -hex 32
SERVER_NAME=:80

DATABASE_URL=postgresql://app:YOURPW@127.0.0.1:5432/app?serverVersion=16&charset=utf8
POSTGRES_PASSWORD=YOURPW

REDIS_URL=redis://127.0.0.1:6379
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages

TELEGRAM_TOKEN=                    # Main bot token from @BotFather
WEBHOOK_TOKEN=                     # Random secret: openssl rand -hex 16
BOT_NAME=                          # Display name for your main bot

BOT_TOKEN_ENCRYPTION_KEY=          # openssl rand -hex 32
DEFAULT_URI=https://yourdomain.com
```

**3. Set up CI/CD**

Push this repo to GitHub. Add these secrets in your repository settings:

| Secret | Value |
|---|---|
| `CAPROVER_SERVER_URL` | `https://captain.yourdomain.com` |
| `CAPROVER_APP_NAME` | App name in CapRover (e.g. `friends-bot`) |
| `CAPROVER_APP_TOKEN` | From App → Deployment tab → Enable App Token |

**4. Deploy**

Push to `main`. The GitHub Actions pipeline will:
1. Run the PHPUnit test suite
2. Build the Docker image and push it to GHCR
3. Tell CapRover to pull and deploy the new image

On first deploy the container will automatically:
- Initialize the PostgreSQL database
- Create the `app` user and enable the `pg_trgm` extension
- Run all database migrations
- Start FrankenPHP, PostgreSQL, Redis, and the Messenger worker under supervisord

**5. Register the main bot webhook**

Once deployed, register the webhook for your main bot:

```
https://yourdomain.com/hook?token=YOUR_WEBHOOK_TOKEN
```

Or via curl:
```bash
curl "https://api.telegram.org/bot<TELEGRAM_TOKEN>/setWebhook" \
  -d "url=https://yourdomain.com/hook&secret_token=YOUR_WEBHOOK_TOKEN"
```

> CapRover uses a **stop-first** strategy for apps with persistent data — every deploy causes a brief outage while the container restarts. For a personal bot this is fine.

---

## Local Development

Requires Docker and Make.

```bash
# Start everything
make start

# Shell into the PHP container
make bash

# Run database migrations
make sf c="doctrine:migrations:migrate"

# Run tests
make test

# Clear Symfony cache
make cc
```

Admin UIs available locally:
- pgAdmin → [http://localhost:5050](http://localhost:5050) (admin@admin.com / admin)
- Redis Insight → [http://localhost:5540](http://localhost:5540)

### Environment

Copy `.env` to `.env.local` and fill in at minimum:

```env
TELEGRAM_TOKEN=your_main_bot_token
WEBHOOK_TOKEN=any_random_string
BOT_NAME=YourBotName
```

For local webhook testing use [ngrok](https://ngrok.com) or similar to expose your local port.

---

## Bot Configuration

Each clone bot has its own tunable parameters, editable from the `/bots` menu:

| Parameter | Default | Description |
|---|---|---|
| **Response probability** | 100% | Chance the bot replies to any given message |
| **Min similarity** | 10% | Minimum trigram similarity to consider a match |
| **Direct reply probability** | 50% | Chance the reply is sent as a direct reply vs. plain message |
| **Response mode** | Direct | `Direct`: reply-pair matching only. `Hybrid`: blends with sequential pairs |
| **Sequential weight** | 30% | *(Hybrid only)* How much weight sequential message pairs get vs. reply pairs |
| **Match limit** | 5 | How many top candidates to randomly pick from |
| **FTS weight** | 0% | How much full-text search score blends into trigram score (0–100%) |
| **Debug mode** | OFF | Shows match trigger, score, and source type inline for tuning |

### Tuning Tips

**Short messages match too broadly?**
The bot automatically raises the similarity floor for short inputs:
1 word → 70%, 2 words → 50%, 3–4 words → 30%, 5–7 words → 20%, 8+ words → your configured floor.

**Clone is too quiet?**
Lower min similarity or raise match limit. Try Response mode: Hybrid.

**Clone is too noisy / replies to everything?**
Lower response probability or raise min similarity.

**Clone ignores @mentions in messages?**
@usernames are automatically stripped before scoring so they don't skew results.

**Want more keyword accuracy?**
Increase FTS weight to 30–50%. This blends full-text search alongside trigram similarity.
A good starting point: FTS weight 40%, min similarity 15%.

---

## Architecture

```
CapRover nginx  (HTTPS/443)
      │
      ▼
Container :80
  ├── frankenphp      PHP 8.4 runtime · Caddy HTTP server
  ├── postgres 16     localhost:5432
  ├── redis           localhost:6379 · AOF persistence
  └── messenger ×2    symfony messenger:consume async
```

```
User sends /start
  └─→ StartCommand
        └─→ AddBotConversation (multi-step wizard)
              ├─→ Validate token via Telegram API
              ├─→ Upload result.json (Telegram or web link)
              ├─→ ProcessChatExportMessage → Redis queue
              │     └─→ TelegramJsonImporter (streaming, batch 300)
              │           └─→ chat_message rows + tsvector GIN index
              └─→ Participant selection
                    └─→ BotWebhookRegistrar → setWebhook()

Incoming message to clone bot
  └─→ POST /bot-hook/{userId}
        └─→ BotWebhookController
              └─→ TriggramBotResponder
                    └─→ ChatMessageRepository::findBestReplyPairs()
                          pg_trgm similarity + optional ts_rank
                    └─→ Random pick from top-N results
                    └─→ sendMessage()
```

### Key Source Paths

| Path | Purpose |
|---|---|
| `src/Entity/` | `Bot`, `ChatExportFile`, `ChatMessage`, `User` |
| `src/Telegram/Conversation/` | Multi-step setup and config flows |
| `src/Telegram/Handler/` | Inline keyboard callback handlers |
| `src/Service/BotResponder/` | `TriggramBotResponder` — core matching logic |
| `src/Repository/ChatMessageRepository.php` | Trigram + FTS SQL queries |
| `src/Controller/BotWebhookController.php` | Clone bot message receiver |
| `src/Controller/ChatExportUploadController.php` | Large file web upload |
| `src/Message/` + `src/MessageHandler/` | Async import pipeline |
| `docker/caprover/` | CapRover supervisord configs and entrypoint |

---

## Stack

- **[FrankenPHP](https://frankenphp.dev)** — PHP runtime with embedded Caddy
- **[Symfony 7.4](https://symfony.com)** — Framework
- **[Nutgram](https://nutgram.dev)** — Telegram bot framework
- **PostgreSQL 16** + **pg_trgm** — Similarity search
- **Redis 7** — Queue transport + cache
- **Symfony Messenger** — Async job processing
- **[halaxa/json-machine](https://github.com/halaxa/json-machine)** — Streaming JSON parser
- **[CapRover](https://caprover.com)** — Self-hosted PaaS

---

## Security Notes

- Each clone bot gets a unique 64-character webhook secret generated on registration. Telegram signs every request and the controller validates it with `hash_equals()` — unsigned requests are silently dropped.
- Clone bot tokens are encrypted at rest using `BOT_TOKEN_ENCRYPTION_KEY`.
- The Symfony secrets vault (`config/secrets/prod/`) is excluded from Docker builds — secrets come from environment variables only.
- Upload links for large files are single-use and tied to a specific user's bot.
