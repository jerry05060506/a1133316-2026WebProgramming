# InfinityFree 部署說明

## 網站網址

正式網址：

`https://secondhandschoolphpfinal.infinityfree.io`

## 使用的資料庫

目前程式設定使用第一個資料庫：

`if0_42205488_secondhandschool`

第二個資料庫 `if0_42205488_secondhandschool2` 可以當備份或測試用。若之後要改用第二個，只要修改 `config.php` 裡的 `db.name`。

## MySQL 連線資料

- Hostname: `sql301.infinityfree.com`
- Port: `3306`
- Username: `if0_42205488`
- Database: `if0_42205488_secondhandschool`

## 要上傳到 htdocs 的檔案

請把以下檔案上傳到 InfinityFree 網站的 `htdocs` 目錄：

- `index.php`
- `api.php`
- `config.php`
- `db.php`
- `mailer.php`

## 匯入資料庫

1. 進入 InfinityFree 控制台。
2. 開啟 phpMyAdmin。
3. 選擇資料庫 `if0_42205488_secondhandschool`。
4. 匯入 `student_marketplace_schema.sql`。

本專案已統整成單一 SQL，請直接匯入 `student_marketplace_schema.sql`。

## 示範帳號

- 平台方：`admin / admin123`
- 買家：`buyer / buyer123`
- 賣家：`seller / seller123`

## 寄信

完成訂單時，系統會透過 `mailer.php` 使用後端 SMTP 設定寄信。買家與賣家註冊時可以使用任何有效 Email，不限定 Gmail。
