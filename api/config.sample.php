<?php
/**
 * デプロイ時に GitHub Secrets から api/config.php が自動で作られます（.github/workflows/deploy.yml）。
 * 手でアップロードする場合は、このファイルを config.php という名前で複製して値を入れてください。
 * config.php はGitに含めないこと（.gitignore 済み）。
 */
return [
    // Notionの「内部インテグレーションシークレット」（ntn_ で始まる文字列）
    'notion_token' => '',

    // 保存先「介護LP_お問い合わせ」のデータソースID（変更不要）
    'notion_data_source_id' => '0b6cac7f-a8fa-4830-a973-6a864272327e',

    // 任意：送信のたびに通知メールも送る場合の宛先
    'notify_email' => '',

    // 送信を受け付けるドメイン（変更不要）
    'allowed_hosts' => ['unou.design', 'www.unou.design'],
];
