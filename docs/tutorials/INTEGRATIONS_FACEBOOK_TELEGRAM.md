# Facebook + Telegram Setup

This project supports Facebook login and Telegram delivery from admin-configurable settings.

## Facebook Login Setup

## 1) Facebook app
- Create an app at Meta for Developers.
- Enable Facebook Login.
- Add redirect URI:
  - `https://your-domain.com/auth/facebook/callback`

## 2) Environment keys

Set these values from **Settings > System Config** or `.env`:

- `FACEBOOK_CLIENT_ID`
- `FACEBOOK_CLIENT_SECRET`
- `FACEBOOK_REDIRECT_URI`

Default callback route exists:
- `GET /auth/facebook/redirect`
- `GET /auth/facebook/callback`

## Telegram Bot Setup

## 1) Create bot
- Open BotFather in Telegram.
- Run `/newbot` and copy bot token.

## 2) Get chat id
- Send a message to the bot.
- Use bot API updates endpoint to find your chat id.

## 3) Environment keys

Set from **Settings > System Config** or `.env`:

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_BOT_USERNAME`
- `TELEGRAM_BOT_WEBHOOK_URL` (optional)

## 4) User-level delivery
- In user settings, set `telegram_chat_id`.
- Enable `receive_telegram_notifications`.

## Validation Checklist

- Facebook redirect opens login dialog.
- Callback creates or links user account.
- Telegram broadcast sends to selected audience.
- Notification tray still receives in-app events.

