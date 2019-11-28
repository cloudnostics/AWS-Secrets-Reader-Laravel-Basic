# AWS-Secrets-Reader-Laravel-Basic
A Basic Laravel helper to read AWS Secrets Manager Secrets

Once you setup your database credentials to auto rotate using AWS secrets manager then you'll need your app to pull the db connection info synamically from AWS Secrets Manager.

Your Laravel app should have a directory called app/Helpers where the file AwsSecretsManagerReader.php should go.

Your composer update/dump-autoload should pick it up.

To setup a DB connection to use your secret, create a new connection in config/database.php something like:


        'mysql_rds' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => AwsSecretsManagerReader::getSecretKey('mysql', 'host'),
            'port' => AwsSecretsManagerReader::getSecretKey('mysql', 'port'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' =>  AwsSecretsManagerReader::getSecretKey('mysql', 'username'),
            'password' => AwsSecretsManagerReader::getSecretKey('mysql', 'password'),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

If you cache your Laravel config then you may need to do something like the following (untested at the moment)


        'mysql_rds' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => function () {
                            return AwsSecretsManagerReader::getSecretKey('mysql', 'host');
                    },
            'port' => function () {
                            return AwsSecretsManagerReader::getSecretKey('mysql', 'port');
                    },
            'database' => env('DB_DATABASE', 'forge'),
            'username' =>  function () {
                            return AwsSecretsManagerReader::getSecretKey('mysql', 'username');
                    },
            'password' =>  function () {
                            return AwsSecretsManagerReader::getSecretKey('mysql', 'password');
                    },
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],



