# UnivaPay-for-EC-CUBE4

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)  

EC-CUBE用のUnivaPay導入プラグイン 
UnivaPayの申し込み方法  
<https://www.univapay.com/service/credit/#12>  

最新のリリースは下記から  
<https://github.com/univapay/UnivaPay-for-EC-CUBE4/releases>

## 開発環境

### ローカル環境の起動
以下のコマンドでDockerを起動します:
```bash
docker-compose up
```

## Webコンテナ内で実行するコマンド

#### 開発環境用
以下のコマンドを実行して、開発環境をセットアップします:
```bash
# php-sdk
bin/console eccube:composer:require univapay/php-sdk:^6.7
# Eccube & プラグイン
bin/console eccube:install -n
bin/console eccube:plugin:install --code=UnivaPay && \
bin/console eccube:plugin:enable --code=UnivaPay && \
bin/console cache:clear --no-warmup
```

#### テスト環境用
```bash
# テスト環境の準備
APP_ENV=test DATABASE_URL=mysql://root:@db:3306/eccubedb_test bin/console eccube:install -n
APP_ENV=test DATABASE_URL=mysql://root:@db:3306/eccubedb_test bin/console eccube:plugin:install --code=UnivaPay
APP_ENV=test DATABASE_URL=mysql://root:@db:3306/eccubedb_test bin/console eccube:plugin:enable --code=UnivaPay
APP_ENV=test DATABASE_URL=mysql://root:@db:3306/eccubedb_test bin/console cache:clear --no-warmup
# テストを実行する
APP_ENV=test DATABASE_URL=mysql://root:@db:3306/eccubedb_test vendor/bin/phpunit app/Plugin/UnivaPay/Tests
```

### アクセス
- **URL**: [http://localhost:1080](http://localhost:1080)
