<?php
/**
 * Copyright 2019 Quantic Ventures Ltd (CloudPeriscope.com). All Rights Reserved.
 *
 * This file is licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License. A copy of
 * the License is located at
 *
 * @license Apache License 2.0
 * @license https://www.apache.org/licenses/LICENSE-2.0.txt
 * @license LICENSE
 *
 * This file is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
 * CONDITIONS OF ANY KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations under the License.
 *
 * Code based on the example at
 * https://docs.aws.amazon.com/code-samples/latest/catalog/php-secretsmanager-GetSecretValue.php.html
 */

namespace App\Helpers;

use Aws\Credentials\CredentialProvider;
use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;

/**
 * This code expects that you have AWS credentials set up per:
 * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html
 *
 */
class AwsSecretsManagerReader {

    public static $aws_client = null;

    public static $version = '2017-10-17';

    public static $secret_name;

    public static $secret_data;

    public static $secret_value;

    private static $refresh_secret = false;

    /**
     * The makeClient method relies on the aws default provider chain
     * https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials.html
     *
     * If there are environment vars present it will use those
     * If there is a shared credentials file it will use that
     * If your EC2 instance/Lambda has a service role it will use that
     *
     * @param array $config
     *
     */
    public static function makeClient(array $config = [])
    {
        try {
            $credentials_provider = null;

            // Use the default credential provider
            // This checks the environment, credentials file an then instance roles
            // see https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.Credentials.CredentialProvider.html#_defaultProvider
            // so you must set env vars, have a credentials file or be running on an ec2 instance with
            // a role that gives access to your resources (secrets manager)

            // check for profile name passed
            $profile = $config['profile'] ?? null;
            if ($profile) {
                unset($config['profile']);
            }

            // check for specific creds passed
            if (isset($config['credentials'])) {
                $credentials_provider = $config['credentials'];
            }

            // todo test ec2 instance role

            // todo test ecs role

            if (!$credentials_provider) {
                $credentials_provider = CredentialProvider::defaultProvider($config);
            }

            $client_options = [
                'version' => self::$version,
                'region' => $config['region'] ?? env('AWS_DEFAULT_REGION', 'eu-west-1'),
            ];

            // add the .credentials profile name in if we have it
            if ($profile) {
                $client_options['profile'] = $profile;
            }

            // if we have a 'truthy' credentials provider then add it in
            if ($credentials_provider) {
                $client_options['credentials'] = $credentials_provider;
            }

            // if credentials or profile not passed in $client_options then the sdk
            // will look for an instance or ecs service role
            self::$aws_client = new SecretsManagerClient($client_options);

        } catch (AwsException $e) {
            $error = $e->getAwsErrorCode();
            // Handle the exception here, and/or rethrow as needed.
            throw $e;
        }
    }

    /**
     * @param $secret_name
     *
     * @return false|mixed|string|null
     */
    public static function getSecret($secret_name)
    {
        try {
            if (!self::$aws_client) {
                self::makeClient();
            }

            // only make an API call if secret doesn't match what we already have
            if ($secret_name !== self::$secret_name || self::$refresh_secret) {
                self::$secret_name = $secret_name;
                self::$refresh_secret = false;

                self::$secret_data = self::$aws_client->getSecretValue([
                    'SecretId' => $secret_name,
                ]);
            }

        } catch (AwsException $e) {
            $error = $e->getAwsErrorCode();

            if ($error === 'DecryptionFailureException') {
                // Secrets Manager can't decrypt the protected secret text using the provided AWS KMS key.
                // Handle the exception here, and/or rethrow as needed.
                throw $e;
            }
            if ($error === 'InternalServiceErrorException') {
                // An error occurred on the server side.
                // Handle the exception here, and/or rethrow as needed.
                throw $e;
            }
            if ($error === 'InvalidParameterException') {
                // You provided an invalid value for a parameter.
                // Handle the exception here, and/or rethrow as needed.
                throw $e;
            }
            if ($error === 'InvalidRequestException') {
                // You provided a parameter value that is not valid for the current state of the resource.
                // Handle the exception here, and/or rethrow as needed.
                throw $e;
            }
            if ($error === 'ResourceNotFoundException') {
                // We can't find the resource that you asked for.
                // Handle the exception here, and/or rethrow as needed.
                throw $e;
            }
        }

        return self::decodeValue();
    }

    /**
     * decode the AWS secrets manager secret as required
     *
     * @return false|mixed|string|null
     */
    public static function decodeValue()
    {
        // Decrypts secret using the associated KMS CMK.
        // Depending on whether the secret is a string or binary, one of these fields will be populated.
        if (self::$secret_data && isset(self::$secret_data['SecretString'])) {
            return self::$secret_data['SecretString'];
        }

        if (self::$secret_data && \is_array(self::$secret_data) && isset(self::$secret_data['SecretBinary'])) {
            return base64_decode(self::$secret_data['SecretBinary']);
        }

        return null;
    }

    /**
     * Force a refresh of the secret
     *
     * @param $secret_name
     *
     * @return false|mixed|string|null
     */
    public static function refreshSecret($secret_name)
    {
        self::$refresh_secret = true;
        return self::getSecret($secret_name);
    }

    /**
     * Get a specific key value from a secrets manager secret
     *
     * @param      $secret_name
     * @param null $key
     *
     * @return false|mixed|string|null
     * @throws \Exception
     */
    public static function getSecretKey($secret_name, $key = null)
    {
        // get the secret from AWS secrets manager
        self::$secret_value = json_decode(self::getSecret($secret_name), true);

        // no key provided so we are expecting a single value, return everything we have
        if (!$key) {
            return self::$secret_value;
        }

        // if we have a key then we are expecting key/value pairs as a JSON string
        if (!isset(self::$secret_value[$key])) {
            throw new \Exception('[CloudPeriscope-AwsSecretsManagerReader:WARN] Secret Key does not exist');
        }

        return self::$secret_value[$key];
    }
}
