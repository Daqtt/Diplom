from telegram import (
    Bot,
    Update,
    ReplyKeyboardMarkup,
    ReplyKeyboardRemove,
)
from telegram.ext import (
    ApplicationBuilder,
    CommandHandler,
    MessageHandler,
    CallbackQueryHandler,
    ContextTypes,
    filters,
)
import logging
from db_config import get_connection

# === CONFIG ===
BOT_TOKEN      = '7924338819:AAFqXKBFo3N24aGXGGLEfQLm9OE4kaxmXwQ'
ADMIN_PASSWORD = '0896ad'

logging.basicConfig(level=logging.INFO)

# Виртуальная память
authenticated_admins = set()       # chat_id админов
awaiting_reply    = {}            # chat_id админ → user_id

# Клавиатуры
kb_request_password = ReplyKeyboardMarkup(
    [['Ввести пароль']],
    resize_keyboard=True,
    one_time_keyboard=False
)

kb_admin = ReplyKeyboardMarkup(
    [['📥 Показать заявки']],
    resize_keyboard=True,
    one_time_keyboard=False
)


async def start(update: Update, context: ContextTypes.DEFAULT_TYPE):
    # При /start просим ввести пароль
    await update.message.reply_text(
        "👋 Добро пожаловать!\n"
        "Чтобы войти как администратор — нажмите кнопку «Ввести пароль» и введите его.\n"
        "Обычные пользователи могут просто написать сообщение для поддержки.",
        reply_markup=kb_request_password
    )


async def handle_message(update: Update, context: ContextTypes.DEFAULT_TYPE):
    chat_id = update.message.chat.id
    text    = update.message.text.strip()

    # Если отправили кнопку «Ввести пароль» — просим ввести сам пароль
    if text == 'Ввести пароль' and chat_id not in authenticated_admins:
        await update.message.reply_text(
            "🔒 Введите админ-пароль:",
            reply_markup=ReplyKeyboardRemove()  # убираем клавиатуру, чтобы не мешала
        )
        return

    # 1) Если пользователь не в списке авторизованных админов
    if chat_id not in authenticated_admins:
        # 1.1) Попытка авторизации
        if text == ADMIN_PASSWORD:
            authenticated_admins.add(chat_id)
            await update.message.reply_text(
                "✅ Пароль принят. Используйте кнопку ниже, чтобы просмотреть заявки:",
                reply_markup=kb_admin
            )
        # 1.2) Обычное сообщение от пользователя → сохраняем и шлём его админам
        else:
            # Сохраняем его в БД
            conn = get_connection()
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO support_messages (user_id, from_admin, text, is_read) VALUES (%s, 0, %s, 0)",
                    (chat_id, text)
                )
                conn.commit()

            # Уведомляем всех админов
            for admin in authenticated_admins:
                await context.bot.send_message(
                    admin,
                    f"📩 Новое сообщение от пользователя {chat_id}:\n\n{text}",
                    reply_markup=kb_admin  # админу по-прежнему показываем кнопку «Показать заявки»
                )
        return

    # 2) Админ уже вошёл → обрабатываем его команды
    if text == '📥 Показать заявки':
        # Тут можно вынести в отдельную функцию, но для примера прямой вариант
        conn = get_connection()
        with conn.cursor() as cur:
            cur.execute("""
                SELECT DISTINCT user_id
                  FROM support_messages
                 WHERE from_admin=0 AND is_read=0
            """)
            rows = cur.fetchall()

        if not rows:
            await update.message.reply_text("Нет новых заявок", reply_markup=kb_admin)
        else:
            buttons = [
                [InlineKeyboardButton(str(r['user_id']), callback_data=f"select:{r['user_id']}")]
                for r in rows
            ]
            markup = InlineKeyboardMarkup(buttons)
            await update.message.reply_text(
                "Выберите пользователя для ответа:",
                reply_markup=markup
            )
        return

    # 3) Если админ уже нажал на inline-кнопку «выбрать пользователя»
    #    то handle_callback разберёт на каком этапе мы

    # 4) Если админ отвечает пользователю (ожидание ввода)
    if chat_id in awaiting_reply:
        user_id = awaiting_reply.pop(chat_id)
        reply   = text
        conn = get_connection()
        with conn.cursor() as cur:
            # сохраняем ответ админа
            cur.execute(
                "INSERT INTO support_messages (user_id, from_admin, text, is_read) VALUES (%s, 1, %s, 1)",
                (user_id, reply)
            )
            # отмечаем все запросы этого пользователя как прочитанные
            cur.execute(
                "UPDATE support_messages SET is_read=1 WHERE user_id=%s AND from_admin=0",
                (user_id,)
            )
            conn.commit()

        await context.bot.send_message(user_id, f"Администратор: {reply}")
        await context.bot.send_message(chat_id, f"✅ Ответ отправлен пользователю {user_id}.", reply_markup=kb_admin)
        return

    # Иначе — ничего не делаем
    return


async def handle_callback(update: Update, context: ContextTypes.DEFAULT_TYPE):
    cq = update.callback_query
    await cq.answer()
    admin_chat = cq.message.chat.id
    data = cq.data

    if data.startswith('select:'):
        _, uid = data.split(':', 1)
        awaiting_reply[admin_chat] = int(uid)
        await context.bot.send_message(
            admin_chat,
            f"✏️ Введите ответ для пользователя {uid}:",
            reply_markup=ReplyKeyboardRemove()
        )


if __name__ == '__main__':
    app = ApplicationBuilder().token(BOT_TOKEN).build()
    # При старте выдаём инструкцию и кнопки
    app.add_handler(CommandHandler('start', start))
    # Обрабатываем callback-запросы от inline-кнопок
    app.add_handler(CallbackQueryHandler(handle_callback))
    # Все текстовые сообщения идут в handle_message
    app.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_message))

    print("🚀 Bot запущен в Polling-режиме.")
    app.run_polling()

