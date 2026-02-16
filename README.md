# 勤怠管理アプリ

## 環境構築
### Dockerビルド
1. git clone git@github.com:kamellll/attendance-management.git
2. docker-compose up -d --build

※MySQLはOSによって起動しない場合があるのでそれぞれのPCに合わせてdocker-compose.ymlファイルを編集してください。

### Laravel環境構築
1. docker-compose exec php bash
2. composer install
3. .env.exampleファイルから.envを作成し、環境変数を変更
4. php artisan key:generate
5. php artisan migrate
6. php artisan db:seed
7. php artisan storage:link
8. docker-compose exec mysql bash
9. mysql -u root -p
10. CREATE DATABASE demo_test;

## 使用技術
・PHP8.1
・Laravel 8.83.29
・MySQL8.0.26

## ER図
![ER図](src/docs/er.svg)

## URL
・開発環境：http://localhost:8008/
・phpMyAdmin：http://localhost:8080/