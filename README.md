# 介護領域向けLP（unou.design/kaigo/）

介護事業者向けに「入居相談が増えるホームページ制作」を提案するLPです。メインテーマは入居者側の集客、採用はサブ。
`main` に入ったファイルは、GitHub Actions で Xserver の `unou.design/public_html/kaigo/` に自動アップロードされます（FTPでの手動アップは不要）。

## ファイル

| ファイル | 内容 |
|---|---|
| `index.html` | LP本体。unou.design/recruit-site/ のスタイル（`assets/css/index-*.css`）に準拠 |
| `assets/css/kaigo.css` | このLP用に追加したスタイル（`.k-` で始まるクラス） |
| `case-neyagawaen.html` | 「事例インタビューを詳しく見る」のリンク先（準備中の仮ページ） |
| `assets/contact.js` | 無料相談フォームの入力チェックと送信 |
| `assets/ga.js` | Googleアナリティクス（G-3TYWNC1DGL／プロパティ unou.design） |
| `api/contact.php` | 送信内容を Notion に保存する処理 |
| `.htaccess` | DM用の短いURL `/kaigo/a`・`/kaigo/b` の転送 |
| `.github/workflows/deploy.yml` | 自動アップロード |
| `wf/` | ワイヤーフレーム（アップロードされない） |

- `a`・`b` という名前のフォルダやファイルは作らない（短いURLとぶつかる）

## 申し込みの流れ

フォーム → `api/contact.php` → Notion「介護LP_お問い合わせ」（データソースID `0b6cac7f-a8fa-4830-a973-6a864272327e`、Notion API 2025-09-03）に1行追加 → Notionのオートメーションで Slack に通知

- 保存項目：法人名・施設名／お名前／メール／電話（任意）／ご質問（任意）／流入元（utm）／対応状況（未対応で入る）
- 送信完了時に GA4 へ `generate_lead`（form_name: kaigo_contact）を送る
- 送信は unou.design 上でのみ動く（ローカルや GitHub Pages では送信エラーになる）

## スパム対策

隠し入力欄、表示から3秒未満の送信の破棄、同一IPから10分間に5件まで、unou.design 以外からの送信の拒否。

## 必要な設定

### GitHub → Settings → Secrets and variables → Actions

| Secret | 値 |
|---|---|
| `FTP_SERVER` | XserverのFTPホスト名（例：sv12345.xserver.jp） |
| `FTP_USERNAME` | kaigo 専用FTPアカウント（例：deploy-kaigo@unou.design） |
| `FTP_PASSWORD` | そのパスワード |
| `FTP_SERVER_DIR` | `./`（専用アカウントの接続先を /unou.design/public_html/kaigo/ にした場合） |
| `NOTION_TOKEN` | Notionインテグレーションのシークレット（recruit-site・hospital と同じもので可） |
| `NOTIFY_EMAIL` | 任意。送信ごとの通知メール宛先（Notion保存に失敗したときの控えにもなる） |

Secrets が未登録のあいだは、アップロードはスキップされます。
`api/config.php` はデプロイ時に Secrets から自動で作られます。リポジトリには入れないでください。

### Notion

- 「介護LP_お問い合わせ」にインテグレーションを接続する（親ページ「採用サイト問い合わせ」に接続済みなら不要）
- Slack通知のオートメーションを追加する（ページが追加されたら #1div_recuit に通知）

## 公開前に確認すること

- FVの「約20倍」の表現（先方は実数を取っていないため、表現を要確認）
- お客様の声（那須さま・辻井さま）の掲載可否と役職表記
- Why us の写真・経歴表記は recruit-site のものを流用
- ヒーロー画像、Approach の図は仮のビジュアル
