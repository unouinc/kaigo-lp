<?php
/**
 * 介護領域向けLP（unou.design/kaigo/）の無料相談の受付
 *
 * フォームの送信内容を受け取り、Notionのデータベース「介護LP_お問い合わせ」に保存します。
 * Slackへの通知はNotionのオートメーションが行います。
 *
 * 設定：同じフォルダの config.php（デプロイ時に GitHub Secrets から自動生成。config.sample.php を参照）
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Method Not Allowed']);
}

/* ---------- 設定 ---------- */
$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
$token        = trim((string) ($config['notion_token'] ?? ''));
$dataSourceId = $config['notion_data_source_id'] ?? '0b6cac7f-a8fa-4830-a973-6a864272327e';
$notifyEmail  = $config['notify_email'] ?? '';
$allowedHosts = $config['allowed_hosts'] ?? ['unou.design', 'www.unou.design'];
$notionApi    = $config['notion_api'] ?? 'https://api.notion.com/v1/pages';

/* ---------- 送信元チェック（他サイトからの送信を拒否） ---------- */
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
$originHost = $origin !== '' ? (string) parse_url($origin, PHP_URL_HOST) : '';
if ($originHost !== '' && !in_array($originHost, $allowedHosts, true)) {
    respond(403, ['ok' => false, 'message' => 'Forbidden']);
}

/* ---------- 入力の読み取り ---------- */
$raw = file_get_contents('php://input') ?: '';
$p = json_decode($raw, true);
if (!is_array($p)) {
    $p = $_POST;
}
$str = static function ($v, int $max): string {
    $v = is_string($v) ? $v : '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
};

/* ---------- ボット対策 ---------- */
// 隠し欄に入力がある、または表示から3秒未満の送信は、成功を返して破棄
$ts = isset($p['ts']) ? (int) $p['ts'] : 0;
if (!empty($p['website']) || ($ts > 0 && (time() - intdiv($ts, 1000)) < 3)) {
    respond(200, ['ok' => true]);
}

// 同じIPから10分間に5件まで
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/kaigo_lp_rl_' . md5($ip);
$now = time();
$hits = [];
if (is_file($rlFile)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string) file_get_contents($rlFile))),
        static fn ($t) => $t > $now - 600
    );
}
if (count($hits) >= 5) {
    respond(429, ['ok' => false, 'message' => '送信が続いたため、しばらく時間をおいてお試しください。']);
}

/* ---------- 入力チェック ---------- */
$d = [
    'company' => $str($p['company'] ?? '', 100),
    'name'    => $str($p['name'] ?? '', 60),
    'email'   => $str($p['email'] ?? '', 120),
    'tel'     => $str($p['tel'] ?? '', 20),
    'message' => $str($p['message'] ?? '', 2000),
    'utm'     => $str($p['utm'] ?? '', 300),
];

$errors = [];
if ($d['company'] === '') {
    $errors['company'] = '法人名・施設名を入力してください。';
}
if ($d['name'] === '') {
    $errors['name'] = 'お名前を入力してください。';
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'メールアドレスを正しく入力してください。';
}
if ($d['tel'] === '') {
    $errors['tel'] = '電話番号を入力してください。';
} elseif (!preg_match('/^[0-9０-９\-－‐+()（）\s]{10,20}$/u', $d['tel'])) {
    $errors['tel'] = '電話番号を正しく入力してください。';
}
if ($errors) {
    respond(422, ['ok' => false, 'errors' => $errors]);
}

/* ---------- Notionへ保存 ---------- */
if ($token === '') {
    error_log('[kaigo/contact] notion_token is not configured');
    send_mail($notifyEmail, $d, true);
    respond(500, ['ok' => false, 'message' => '送信できませんでした。時間をおいて再度お試しください。']);
}

$text = static fn (string $s): array => $s === '' ? [] : [['type' => 'text', 'text' => ['content' => $s]]];

$props = [
    '法人名・施設名' => ['title' => $text($d['company'])],
    'お名前'      => ['rich_text' => $text($d['name'])],
    'メールアドレス'  => ['email' => $d['email']],
    'ご質問'      => ['rich_text' => $text($d['message'])],
    '流入元'      => ['rich_text' => $text($d['utm'])],
    '対応状況'     => ['status' => ['name' => '未対応']],
];
if ($d['tel'] !== '') {
    $props['電話番号'] = ['phone_number' => $d['tel']];
}

$ch = curl_init($notionApi);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Notion-Version: 2025-09-03',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode(['parent' => ['type' => 'data_source_id', 'data_source_id' => $dataSourceId], 'properties' => $props], JSON_UNESCAPED_UNICODE),
]);
$resBody = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($code !== 200) {
    error_log('[kaigo/contact] Notion error: ' . $code . ' ' . ($curlErr ?: (string) $resBody));
    send_mail($notifyEmail, $d, true);
    $notionCode = (string) (json_decode((string) $resBody, true)['code'] ?? ($curlErr !== '' ? 'curl_error' : 'unknown'));
    respond(502, ['ok' => false, 'message' => '送信できませんでした。時間をおいて再度お試しください。', 'code' => 'notion_' . $code . '_' . $notionCode]);
}

$hits[] = $now;
@file_put_contents($rlFile, implode(',', $hits), LOCK_EX);
send_mail($notifyEmail, $d, false);
respond(200, ['ok' => true]);

/* ---------- 通知メール（任意。Notion保存に失敗したときの控えにもなる） ---------- */
function send_mail(string $to, array $d, bool $failed): void
{
    if ($to === '') {
        return;
    }
    mb_language('Japanese');
    mb_internal_encoding('UTF-8');
    $subject = ($failed ? '【要確認・Notion保存失敗】' : '') . '【介護LP】無料相談のお申し込み：' . $d['company'];
    $body = "法人名・施設名：{$d['company']}\nお名前：{$d['name']}\nメールアドレス：{$d['email']}\n"
        . "電話番号：{$d['tel']}\n流入元：{$d['utm']}\nご質問：\n{$d['message']}\n";
    $replyTo = filter_var($d['email'], FILTER_VALIDATE_EMAIL) ? $d['email'] : $to;
    @mb_send_mail($to, $subject, $body, 'From: ' . $to . "\nReply-To: " . $replyTo, '-f ' . $to);
}
