<?php
require __DIR__ . '/../vendor/autoload.php';

use Telegram\Bot\Api;

$config = require __DIR__ . '/../config.php';
$telegram = new Api($config['bot_token']);

$pdo = new PDO(
    "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset=utf8mb4",
    $config['db']['user'],
    $config['db']['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$update = json_decode(file_get_contents('php://input'), true);

function checkSubscription($telegram, $userId, $channels) {
    foreach ($channels as $channel) {
        try {
            $status = $telegram->getChatMember(['chat_id' => $channel, 'user_id' => $userId])->status;
            if (!in_array($status, ['member', 'creator', 'administrator'])) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }
    return true;
}

if (isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $user_id = $msg['from']['id'];
    $text = $msg['text'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() == 0) {
        $pdo->prepare("INSERT INTO users (telegram_id, name, username, language, joined_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([
                $user_id,
                $msg['from']['first_name'] ?? '',
                $msg['from']['username'] ?? '',
                'uz'
            ]);
    }

    if ($text == '/start') {
        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "Assalomu alaykum! Botga xush kelibsiz.\nTilni tanlang:",
            'reply_markup' => json_encode([
                'keyboard' => [[['text' => "O'zbek"], ['text' => "Русский"]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true
            ])
        ]);
    } elseif (in_array($text, ["O'zbek", "Русский"])) {
        $lang = $text == "O'zbek" ? 'uz' : 'ru';
        $pdo->prepare("UPDATE users SET language = ? WHERE telegram_id = ?")->execute([$lang, $user_id]);

        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "Til tanlandi: $text",
            'reply_markup' => json_encode([
                'keyboard' => [
                    [['text' => '🎯 Viktorina']],
                    [['text' => '📊 Natijam'], ['text' => '🏆 Top 10']],
                    [['text' => '📋 Qoidalar'], ['text' => '✅ Obuna tekshirish']]
                ],
                'resize_keyboard' => true
            ])
        ]);
    } elseif ($text == '✅ Obuna tekshirish') {
        $channels = $config['required_channels'];
        if (checkSubscription($telegram, $user_id, $channels)) {
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "✅ Siz barcha kerakli kanallarga obuna bo‘lgansiz."
            ]);
        } else {
            $msg = "❗ Quyidagi kanallarga obuna bo‘ling:\n";
            foreach ($channels as $ch) {
                $msg .= "➤ https://t.me/" . ltrim($ch, '@') . "\n";
            }
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $msg
            ]);
        }
    } elseif ($text == '🎯 Viktorina') {
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE question_date = ?");
        $stmt->execute([$today]);
        if ($q = $stmt->fetch()) {
            $opts = json_decode($q['options'], true);
            $inline = [];
            foreach ($opts as $i => $opt) {
                $inline[] = [[
                    'text' => $opt,
                    'callback_data' => "answer_{$q['id']}_$i"
                ]];
            }
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => $q['text'],
                'reply_markup' => json_encode(['inline_keyboard' => $inline])
            ]);
        } else {
            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => "Bugungi savol mavjud emas."
            ]);
        }
    } elseif ($text == '📊 Natijam') {
        $stmt = $pdo->prepare("SELECT points FROM users WHERE telegram_id = ?");
        $stmt->execute([$user_id]);
        $res = $stmt->fetch();
        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "Sizning ballaringiz: " . ($res['points'] ?? 0)
        ]);
    } elseif ($text == '🏆 Top 10') {
        $res = $pdo->query("SELECT name, points FROM users ORDER BY points DESC LIMIT 10")->fetchAll();
        $msg = "🏆 TOP 10:\n";
        foreach ($res as $i => $r) {
            $msg .= ($i+1) . ". {$r['name']} - {$r['points']} ball\n";
        }
        $telegram->sendMessage(['chat_id' => $chat_id, 'text' => $msg]);
    } elseif ($text == '📋 Qoidalar') {
        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => "📋 Qoidalar:\n1. Har kuni 1 savol.\n2. To‘g‘ri javobga 1 ball.\n3. Top 10 ga kirishga harakat qiling!"
        ]);
    }
}

if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $data = $cb['data'];
    $chat_id = $cb['message']['chat']['id'];
    $user_id = $cb['from']['id'];

    if (strpos($data, 'answer_') === 0) {
        list(, $qid, $ans) = explode('_', $data);
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$qid]);
        if ($q = $stmt->fetch()) {
            $correct = $q['correct_index'];
            $isCorrect = ((int)$ans === (int)$correct);

            if ($isCorrect) {
                $pdo->prepare("UPDATE users SET points = points + 1 WHERE telegram_id = ?")
                    ->execute([$user_id]);
            }

            $telegram->sendMessage([
                'chat_id' => $chat_id,
                'text' => ($isCorrect ? "✅ To‘g‘ri javob!" : "❌ Noto‘g‘ri javob.") .
                    "\n\nℹ️ " . $q['explanation']
            ]);
        }
    }
}
