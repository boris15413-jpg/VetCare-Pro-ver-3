# VetCare Pro - 動物病院向け電子カルテシステム

**VetCare Pro** は、小規模〜中規模の動物病院向けに設計された、軽量かつ多機能な電子カルテシステム（EMR）です。
患畜管理、診療記録（SOAP）、会計、在庫管理、予約管理を一元化し、獣医療の現場をサポートします。

## 🚀 主な機能

* **ダッシュボード**: 予約状況、入院状況、未処理オーダーの一覧表示
* **患畜・飼い主管理**: 基本情報、既往歴、アレルギー、マイクロチップ情報の管理
* **電子カルテ (SOAP)**: 写真添付対応の診療記録、過去歴の参照
* **入院管理**: 投薬・バイタルサイン記録（温度板機能）
* **オーダー管理**: 処方、検査、処置のオーダーと実施状況管理
* **会計・請求**: 領収書、明細書、処方箋の発行
* **在庫管理**: 薬品・消耗品の在庫管理と発注点アラート
* **各種書類作成**: 紹介状、診断書、ワクチン証明書などの作成・印刷
* **ユーザー管理**: 獣医師、看護師、受付などの権限管理（RBAC）

---

## 🛠️ 動作環境

このシステムは標準的な **PHP** 環境で動作します。

* **PHP**: 7.4 以上 (8.0以上推奨)
* **データベース**: SQLite (デフォルト) または MySQL/MariaDB
* **Webサーバー**: Apache, Nginx など

---

## 📦 インストールとセットアップ手順

### 1. ローカル環境 (XAMPP / MAMP) の場合

個人のPCでテストや開発を行う場合の手順です。

1. **XAMPP (Windows) または MAMP (Mac) をインストール**します。
2. `htdocs` フォルダ内に `vetcare` フォルダを作成し、すべてのファイルを配置します。
3. ブラウザで `http://localhost/vetcare/` にアクセスします。
4. 自動的に **セットアップ画面 (インストーラー)** にリダイレクトされます。
5. 画面の指示に従い、以下の手順を実行します。
* Step 1: データベースの初期化
* Step 2: サンプルデータの投入（任意）
* Step 3: 管理者アカウントの作成


6. セットアップ完了後、ログイン画面からログインしてください。

### 2. 一般的なWebサーバー (VPS / レンタルサーバー) の場合

**注意**: SQLiteを使用する場合、データベースファイルへの直接アクセスを防ぐためのセキュリティ設定が必要です。

1. サーバー上の公開ディレクトリ（`public_html` 等）にファイルをアップロードします。
2. 以下のディレクトリに **書き込み権限 (707 または 755/777)** を付与してください。
* `data/` (SQLiteデータベース保存用)
* `uploads/` (画像アップロード用)
* `backups/` (バックアップ保存用)


3. ブラウザでURLにアクセスし、インストーラーを実行します。
4. **セキュリティ対策**: `data/` ディレクトリにはブラウザから直接アクセスできないように `.htaccess` 等で制限をかけてください（システムには組み込み済みですが、サーバー環境によります）。

---

## 🗄️ データベース設定の変更 (MySQLの使用)

デフォルトでは手軽に利用できる **SQLite** が設定されていますが、本番環境や大規模な運用の場合は **MySQL (MariaDB)** の使用を推奨します。

設定は `includes/config.php` で行います。

1. `includes/config.php` をテキストエディタで開きます。
2. `DB_DRIVER` の値を `'sqlite'` から `'mysql'` に変更します。
3. MySQLの接続情報を入力します。

```php
// includes/config.php

// データベース設定
// 'sqlite' または 'mysql' を指定
define('DB_DRIVER', 'mysql'); // ← ここを変更

// SQLite設定
define('DB_SQLITE_PATH', BASE_PATH . '/data/vetcare.db');

// MySQL設定
define('DB_MYSQL_HOST', 'localhost');     // ホスト名 (例: 127.0.0.1)
define('DB_MYSQL_NAME', 'vetcare_db');    // データベース名
define('DB_MYSQL_USER', 'root');          // ユーザー名
define('DB_MYSQL_PASS', 'your_password'); // パスワード
define('DB_MYSQL_CHARSET', 'utf8mb4');

```

4. 設定保存後、ブラウザでアクセスすると再度インストーラーが起動しますので、テーブル作成を行ってください。

---

## 🔧 開発・Cloudflare Workers 環境について

本プロジェクトには、フロントエンド開発および Cloudflare Workers (Hono) へのデプロイ用の設定ファイルが含まれています。
これらは主に静的アセットの配信や、将来的なサーバーレス環境への移行、あるいはAPIサーバーとしての利用を想定しています。

通常のPHP環境で利用する場合は、以下のコマンドを実行する必要はありません。

### 開発コマンド (Frontend / Workers)

```txt
npm install
npm run dev

```

### デプロイ (Cloudflare Pages / Workers)

```txt
npm run deploy

```

[For generating/synchronizing types based on your Worker configuration run](https://developers.cloudflare.com/workers/wrangler/commands/#types):

```txt
npm run cf-typegen

```

Pass the `CloudflareBindings` as generics when instantiation `Hono`:

```ts
// src/index.ts
const app = new Hono<{ Bindings: CloudflareBindings }>()

```

---

## ⚠️ 注意事項

* **セキュリティ**: `pages/update_db.php` や `pages/install.php` は、運用開始後にサーバーから削除することを強く推奨します。
* **バックアップ**: 管理画面の「セキュリティ」タブから手動バックアップが可能です（SQLiteのみ）。定期的なバックアップを行ってください。

## ライセンス

This project is open source software.

