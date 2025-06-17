# support_bot.py

# =============================================================================
# === PRODUCTION MODE (Webhook) ================================================
# =============================================================================
# To deploy on your server with HTTPS/webhook, UNCOMMENT this entire block
# and COMMENT OUT (or remove) the polling section at the bottom.

# from flask import Flask, request, abort
# from telegram import Bot, Update, InlineKeyboardButton, InlineKeyboardMarkup
# import logging, requests
# from db_config import get_connection
#
# # === CONFIG ===
# BOT_TOKEN      = '7924338819:AAFqXKBFo3N24aGXGGLEfQLm9OE4kaxmXwQ'
# WEBHOOK_URL    = 'https://your-domain.com/webhook'
# SECRET_TOKEN   = 'очень_секретная_строка'
# ADMIN_PASSWORD = '0896ad'
#
# app = Flask(__name__)
# bot = Bot(token=BOT_TOKEN)
# logging.basicConfig(level=logging.INFO)
#
# # In-memory tracking
# authenticated_admins = set()
# awaiting_reply = {}
#
# @app.route('/webhook', methods=['POST'])
# def webhook():
#     # verify Telegram secret token header
#     if request.headers.get('X-Telegram-Bot-Api-Secret-Token') != SECRET_TOKEN:
#         abort(403)
#
#     upd = Update.de_json(request.get_json(force=True), bot)
#     # ... (same callback_query and message handlers as polling version) ...
#     return 'OK'
#
# @app.route('/support.php', methods=['POST'])
# def support_from_site():
#     # receive from PHP → save to DB → notify authenticated_admins
#     data = request.get_json(force=True)
#     # ... (same logic as polling version) ...
#     return 'OK'
#
# if __name__ == '__main__':
#     # register webhook at startup
#     try:
#         requests.get(
#             f'https://api.telegram.org/bot{BOT_TOKEN}/setWebhook',
#             params={'url': WEBHOOK_URL, 'secret_token': SECRET_TOKEN}
#         )
#     except Exception as e:
#         logging.error(f'Webhook registration failed: {e}')
#     app.run(host='0.0.0.0', port=5000)
#
# =============================================================================
# === END PRODUCTION MODE =====================================================
# =============================================================================


# =============================================================================
# === TESTING MODE (Polling) ==================================================
# =============================================================================
# For local Windows testing without SSL/ngrok, LEAVE this block uncommented.
# When deploying to production, REMOVE or COMMENT OUT everything from here down.

from telegram import Bot, Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import (
    ApplicationBuilder,
    CallbackQueryHandler,
    MessageHandler,
    ContextTypes,
    filters,
)
import logging
from db_config import get_connection

# === CONFIG ===
BOT_TOKEN      = '7924338819:AAFqXKBFo3N24aGXGGLEfQLm9OE4kaxmXwQ'
ADMIN_PASSWORD = '0896ad'

logging.basicConfig(level=logging.INFO)

# In-memory tracking
authenticated_admins = set()
awaiting_reply = {}  # chat_id -> user_id


async def handle_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    cq = update.callback_query
    await cq.answer()
    admin_chat = cq.message.chat.id
    data = cq.data

    if data == 'show_pending':
        conn = get_connection()
        with conn.cursor() as cur:
            cur.execute("""
                SELECT DISTINCT user_id
                  FROM support_messages
                 WHERE from_admin=0 AND is_read=0
            """)
            rows = cur.fetchall()

        if not rows:
            await cq.message.reply_text("Нет новых заявок")
        else:
            buttons = [
                [InlineKeyboardButton(str(r['user_id']), callback_data=f"select:{r['user_id']}")]
                for r in rows
            ]
            markup = InlineKeyboardMarkup(buttons)
            await context.bot.send_message(
                admin_chat,
                "Выберите пользователя для ответа:",
                reply_markup=markup
            )

    elif data.startswith('select:'):
        _, uid = data.split(':', 1)
        awaiting_reply[admin_chat] = int(uid)
        await context.bot.send_message(
            admin_chat,
            f"Введите ответ для пользователя {uid}:"
        )


async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    msg = update.message
    chat_id = msg.chat.id
    text    = msg.text.strip()

    # 1) Authenticate
    if chat_id not in authenticated_admins:
        if text == ADMIN_PASSWORD:
            authenticated_admins.add(chat_id)
            btn = InlineKeyboardButton("📥 Показать заявки", callback_data='show_pending')
            markup = InlineKeyboardMarkup([[btn]])
            await context.bot.send_message(
                chat_id,
                "✅ Пароль принят. Нажмите кнопку, чтобы увидеть заявки:",
                reply_markup=markup
            )
        return

    # 2) If awaiting a reply to a selected user
    if chat_id in awaiting_reply:
        user_id = awaiting_reply.pop(chat_id)
        reply   = text
        conn = get_connection()
        with conn.cursor() as cur:
            # save admin response
            cur.execute(
                "INSERT INTO support_messages (user_id, from_admin, text, is_read) "
                "VALUES (%s,1,%s,1)",
                (user_id, reply)
            )
            # mark all that user's requests as read
            cur.execute(
                "UPDATE support_messages SET is_read=1 "
                "WHERE user_id=%s AND from_admin=0",
                (user_id,)
            )
            conn.commit()

        await context.bot.send_message(user_id, f"Администратор: {reply}")
        await context.bot.send_message(chat_id, f"✅ Ответ отправлен пользователю {user_id}.")
        return

    # 3) Otherwise ignore
    return


if __name__ == '__main__':
    app = ApplicationBuilder().token(BOT_TOKEN).build()
    app.add_handler(CallbackQueryHandler(handle_callback))
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))

    print("🚀 Polling-режим запущен. Пишите боту в Telegram.")
    app.run_polling()

# =============================================================================
# === END TESTING MODE ========================================================
# =============================================================================
