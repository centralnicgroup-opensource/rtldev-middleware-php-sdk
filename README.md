# php-sdk

[![semantic-release](https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg)](https://github.com/semantic-release/semantic-release)
[![Build Status](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/workflows/Release/badge.svg?branch=master)](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/workflows/Release/badge.svg?branch=master)
[![Packagist](https://img.shields.io/packagist/v/centralnic-reseller/php-sdk.svg)](https://packagist.org/packages/centralnic-reseller/php-sdk)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/centralnic-reseller/php-sdk.svg)](https://packagist.org/packages/centralnic-reseller/php-sdk)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/blob/master/CONTRIBUTING.md)

This module is a connector library for the insanely fast CNIC Backend APIs (HEXONET, CentralNic Reseller formerly known as RRPproxy). Do not hesitate to contact us in case of questions.

## Resources

* Documentation:
    * [HEXONET](https://www.hexonet.support/hc/en-gb/articles/13651711901213-Self-Development-Kit-for-PHP)
    * [CentralNic Reseller](https://support.centralnicreseller.com/hc/en-gb/articles/13513253776285-Self-Development-Kit-for-PHP)
* [Release Notes](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/releases)

## Usage

```composer require centralnic-reseller/php-sdk```

## Dev Container
* Add an entry in your hosts file: ```127.0.0.1         devsdk.hexonet.net``` 

PHP SDK Data can be accessed via apache server at this url: ```http://devsdk.hexonet.net```

## Running the Demo Application

To run the demo application, follow these steps:

1. **Set Your Credentials**:
   You need to ensure your credentials are available. You can do this in two ways:
   - Directly replace the credentials within the application file.
   - Alternatively, set the environment variables required for the CNR test app:
     ```sh
     export RTLDEV_MW_CI_USER_CNR=<your-username>
     export RTLDEV_MW_CI_USERPASSWORD_CNR=<your-password>
     ```

2. **Execute the Demo**: Once the credentials are configured, run the appropriate demo command:
   - For CNR:
     ```sh
     npm run test-demo-cnr
     ```
   - For Hexonet:
     ```sh
     npm run test-demo-hexonet
     ```

3. **Update Demo Contents**:
   If you need to modify the demo contents, the relevant file is located at:
   - For CNR:
   ```plaintext
   tests/CNR/app.php
   ```

   - For Hexonet:
   ```plaintext
   tests/HEXONET/app.php
   ```

## Authors

* **Kai Schwarz** - *development* - [KaiSchwarz-cnic](https://github.com/kaischwarz-cnic)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
