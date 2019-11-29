# AWS Secrets Reader for Laravel (Basic)
A Basic Laravel helper to read AWS Secrets Manager Secrets

## Installation
#### Add the helper class to your Laravel App (sorry, it is a manual copy at this point) 
* Create a directory called app/Helpers 
* Copy the file AwsSecretsManagerReader.php to app/Helpers

Run composer dump-autoload to generate the autoload class map
```bash
composer dump-autoload
```
If you are using AWS credentials from your `.aws/credentials` then you will need to add the parent directory of .aws as a `HOME` environment variable.

e.g if your .aws dir is in `/home/yourusername` then add the following to your .env

```dotenv
HOME=/home/yourusername
```

The AWS sdk should pickup the default profile from your credentials file, alternatively you can set your AWS aAccess Key and Secret in your .env file using the entries

```dotenv
AWS_ACCESS_KEY_ID={YOUR_ACCESS_KEY}
AWS_SECRET_ACCESS_KEY={YOUR_SECRET_}
```

You can also set your default region using the `AWS_REGION` environment variable.  This should be the region where your secrets are located.

```dotenv
AWS_REGION=eu-west-1
```

## Basic Usage

####To retrieve the whole secret

```php
$secret = AwsSecretsManagerReader::getSecret('{SECRET_NAME}');
``` 

####To retrieve a specific value of the secret, ask for it by it's key e.g.
```php
AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'username');
AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'password');
AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'engine');
AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'host');
AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'port');
```

####To refresh the secret from AWS

The AwsSecretsManagerReader class will try to minimise the calls to AWS for fetching secrets and cache it during each run.

If you have a long running process and need to force a fetch of the secret from AWS you can do this using hte `refreshSecret` call.

```php 
AwsSecretsManagerReader::refreshSecret('{SECRET_NAME}');
```
- - -
#### Setup a Laravel database connection to use your secret

Once you setup your database credentials to auto rotate using AWS secrets manager then you'll need your app to pull the db connection info dynamically from AWS Secrets Manager.
[https://aws.amazon.com/blogs/security/rotate-amazon-rds-database-credentials-automatically-with-aws-secrets-manager/]()


Create a new connection in config/database.php something like:
Replace `{SECRET_NAME}` with the name of of your AWS secret. 

```php
'mysql_rds' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'host'),
    'port' => AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'port'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' =>  AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'username'),
    'password' => AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'password'),
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
```


** Untested **

If you cache your Laravel config then you may need to do something like the following (untested at the moment)
```php
'mysql_rds' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => function () {
                    return AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'host');
            },
    'port' => function () {
                    return AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'port');
            },
    'database' => env('DB_DATABASE', 'forge'),
    'username' =>  function () {
                    return AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'username');
            },
    'password' =>  function () {
                    return AwsSecretsManagerReader::getSecretKey('{SECRET_NAME}', 'password');
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
```
To use the the connection you need to set DB_CONNECTION in your .env file to use the new connection
```dotenv
DB_CONNECTION=mysql_rds
```

## Custom Configuration and credentials

This code uses the official AWS SDK and it's built in default credentials provider.

Please see [https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html]() for more information.

You can pass configuration information in to the the class by creating a config array and calling the static method `makeClient` with it.  You can also pass in custom credentials using this method.

Create a config array
```php
$config = [];
``` 
- - -
To set the region that holds your secrets
```php
$config['region'] = 'us-east-2';
``` 
- - -
If you have several profiles of credentials in your .aws/credentials file you can specify which profile to use.

```php
$config['profile'] = 'db-creds';
``` 
The correseponding entry in your `.aws/credentials` file would be
```bash
[db-creds]
aws_access_key_id={YOUR_ACCESS_KEY}
aws_secret_access_key={YOUR_ACCESS_SECRET}
```
- - -
If you want to pass in specific AWS API secrets you can specify them in the config. 

If we had a separate ACCESS KEY and SECRET in just for retrieving DB creds from AWS Secrects Manager our .env file entries may look like this.
```php
DB_SECRET_ACCESS_KEY={YOUR_ACCESS_KEY}
DB_SECRET_ACCESS_SECRET={YOUR_ACCESS_SECRET}
``` 
We can add them to our config using 
```php
$config['credentials'] = [
       'key' => env('DB_SECRET_ACCESS_KEY'),
       'secret' => env('DB_SECRET_ACCESS_SECRET'),
     ];
```
- - -
Then pass the configuration to the `makeClient` method
```php
AwsSecretsManagerReader::makeClient($config);
``` 
Any calls to `getSecret()` and `getSecretKey()` will use the config you provided.

 
    
## Contributing
Pull requests are welcome. 

For major changes, please open an issue first to discuss what you would like to change.


## License
[Apache License 2.0](./LICENSE.md)