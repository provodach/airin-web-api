<?php

// Fill in the config params and rename me to enconfig.php

// Database
define ('DB_TYPE', 'pg'); // my = MySQL; pg - PostgreSQL
define ('DB_HOST', 'localhost');
define ('DB_NAME', 'airin');
define ('DB_USER', 'airin');
define ('DB_PASSWORD', '_change_me_plz');

// VK Auth
define ('CLIENT_ID', 31337228);
define ('CLIENT_SECRET', '_change_me_');
define ('REDIRECT_URI', 'https://example.com/airin/auth'); // The trailing /auth is mandatory

// System settings
define ('DEFAULT_AMOUNT', 50);
define ('MAX_MESSAGE_AMOUNT', 32);
define ('TEMP_CODE_SALT', '_change_me_with_pure_randomness_');
define ('CURRENT_TRACK_FILE', '/var/run/liquidsoap/provodach.track'); // Liqudsoap script will place the file path here
define ('CURRENT_TAG_FILE', '/var/run/liquidsoap/provodach.tag'); // Liquidsoap script will place the track metadata here

// Telegram
define ('TELEGRAM_BOT_TOKEN', ''); // Obtain in from @BotFather
define ('TELEGRAM_CALLBACK_KEY', '_change_me_with_pure_randomness_');
define ('TELEGRAM_USE_DIRECT_RESPONSE', true); // respond directly or use HTTP API
define ('TELEGRAM_AUDIO_SAVE_PATH', '/srv/storage/telegram'); // no trailing slash!

// Yandex Payments
define ('YANDEX_MONEY_CALLBACK_SECRET', '_change_me_');