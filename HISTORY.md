# [9.1.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v9.0.1...v9.1.0) (2025-01-13)


### Bug Fixes

* **cnr, hx:** reviewed array typing ([0bfe71b](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/0bfe71b483751b8cbfcdabaa5a555c0c34e662cb))
* **phpstan:** reviewed codebase and dropped `treatPhpDocTypesAsCertain: false` setting ([a1dd4e1](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/a1dd4e17bdee1257167d4dfcb07b0a8c7bebf747))


### Features

* **CommandFormatter.php:** enhance command sorting and formatting logic ([affd488](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/affd488e750208a6fbff1310bb46c09b854c0225))

## [9.0.1](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v9.0.0...v9.0.1) (2024-09-16)


### Bug Fixes

* **cnr / hx:** moved functions saveSession & reuseSession to SessionClient Class; reviewed for CNR ([311fc70](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/311fc70d5f55b9fe8794227904970c9c28bd0c56))

# [9.0.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.17...v9.0.0) (2024-06-12)


### Bug Fixes

* **php-stan-compatibility:** ensure compatibility with PHP 8.1 standards ([d5994a8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/d5994a8cf5d212d690139e609f42558488ec0601))


### BREAKING CHANGES

* **php-stan-compatibility:** This update requires PHP 8.1 or higher and may break on systems using PHP versions lower than 8.1.

## [8.0.17](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.16...v8.0.17) (2024-04-05)


### Performance Improvements

* **client.php & sessionclient.php:** reviewed IDN Conversion with php-idna-translator plugin ([b0001ad](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/b0001ad1df59ac259e4248256435503681c78f91))

## [8.0.16](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.15...v8.0.16) (2024-3-4)


### Bug Fixes

* **responsetranslator.php:** reviewed error messaging for domain transfers pre-checks ([c003b9b](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/c003b9bd27d874ffa410333ad5e70e603742a7d3))

## [8.0.15](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.14...v8.0.15) (2023-10-04)


### Bug Fixes

* **hexonet:** add response translator mapping for invalid domain name; RSRMID-271 ([5c40a9d](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/5c40a9d28d58b42eb1f1154e550c602b47652652))

## [8.0.14](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.13...v8.0.14) (2023-09-08)


### Bug Fixes

* **responsetranslator.php:** updated response messages when premium price is not available ([e6939ce](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/e6939cebda513db7ed029a5e2800018f50ab3ff5))

## [8.0.13](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.12...v8.0.13) (2023-09-01)


### Bug Fixes

* **auto idn converting:** of OBJECTID parameter if OBJECTCLASS matches expectations (Registrar CNR) ([a105535](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/a105535e99335b95148f77cccd3ddd4e36c01e9c))

## [8.0.12](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.11...v8.0.12) (2023-08-29)


### Bug Fixes

* **http error:** details are now added to the response ([316194e](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/316194e293d0804581bfb5823c034771b9d90dd6))

## [8.0.11](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.10...v8.0.11) (2023-08-24)


### Bug Fixes

* **hexonet/responsetranslator.php:** auto-reword DNS update error ([f5bdc51](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/f5bdc51b8f26ce40704756441e89c735c18ba47c))

## [8.0.10](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.9...v8.0.10) (2023-06-19)


### Bug Fixes

* **docker-compose:** upgrade to PHP 7.4 (required for phpDocumentor 3.3.1) ([d6e06e8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/d6e06e8ef6b9a98104ada35520760e7eeb032a8e))
* **dockerfile:** upgrade to PHP 7.4 (requirement for phpDocumentor 3.3.1) ([517754a](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/517754a5f7c0ec3d783b11cfed55bf3f66f4b20d))
* **gh actions:** use PHP 7.4 (requirement for phpDocumentor 3.3.1) ([081ac88](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/081ac88ff8571dda3f3f28ed0691a9243eb2354f))
* **phpdocumentor:** upgrade to 3.3.1 case (sentivity bug of produced file names) ([4803f86](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/4803f864fea58c5c8f205930ba7dfccbb79284eb)), closes [#40](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/issues/40)

## [8.0.9](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.8...v8.0.9) (2023-04-18)


### Bug Fixes

* **gitattributes:** ignore non essential files from composer archive ([534e4b8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/534e4b8e7bacf32cdae5a552e38f5210f44f2dd4))

## [8.0.8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.7...v8.0.8) (2023-04-18)


### Bug Fixes

* **gitattributes:** ignore non essential ([b61dab2](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/b61dab2c5f547aa78981c3576ffc1340586bec24))

## [8.0.7](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.6...v8.0.7) (2023-04-18)


### Bug Fixes

* **z:** exclude non essentials ([141f13d](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/141f13dd03d6762237ddfd6f6394f9801c2e5b85))

## [8.0.6](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.5...v8.0.6) (2023-04-18)


### Bug Fixes

* **gitattribute:** exclude non essentials files and folders ([1fd7429](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/1fd742956c65a3c0748636257d81f5981772f9cb))

## [8.0.5](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.4...v8.0.5) (2023-03-23)


### Bug Fixes

* **curl options:** cURLOPT_TIMEOUT and CURLOPT_CONNECTTIMEOUT to consider seconds not ms ([d32827f](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/d32827f4ed770151e0d8b3690c11d512b9d5f418))

## [8.0.4](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.3...v8.0.4) (2022-11-18)


### Bug Fixes

* **client:** response class dependency injection and restructure ([4a40c73](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/4a40c73e735a89f6a15f68f57070dfe0755c01f1))

## [8.0.3](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.2...v8.0.3) (2022-10-20)


### Bug Fixes

* **columns:** ignore pagination columns (TOTAL|COUNT|LAST|LIMIT|FIRST) in non-pagination contexts ([37685dd](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/37685dd1d2d90fa85ce3e668fd4e8f7b1ef4c652))

## [8.0.2](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.1...v8.0.2) (2022-10-07)


### Bug Fixes

* **auto idn convert:** patched to not consider empty command parameters ([00c7787](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/00c77878a10dffccb80c3fc9e7c38640c80af011))

## [8.0.1](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v8.0.0...v8.0.1) (2022-08-16)


### Bug Fixes

* **clientfactory:** added support for several other registrar ids for better user experience ([056348e](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/056348e1af1e8d937a738da6c43f988b3c456d8c))

# [8.0.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.11...v8.0.0) (2022-08-04)


### Bug Fixes

* **responsetranslator:** to return empty description case also as `invalid` template; patched tests ([63eb230](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/63eb230fb72dca618508f1004acf34c1e73e5d1a))


### chore

* **centralnic reseller:** introduced as replacement for RRPproxy ([2e66cdc](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/2e66cdc0fbef52cada52579aaab6b4cc79b085dc))


### BREAKING CHANGES

* **centralnic reseller:** RRPproxy marked for deprecation and falling back to new Brand Name "CentralNic
Reseller" (CNR). Nothing changes mainly, CNR is the new label for RRP and we want to reflect this
also in our software.

## [7.1.11](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.10...v7.1.11) (2022-07-15)


### Bug Fixes

* **automatic idn conversion:** reviewed in direction of case insensitive patterns ([5166b84](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/5166b84681de69fff963d27d87bd09541669191e))

## [7.1.10](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.9...v7.1.10) (2022-07-12)


### Bug Fixes

* **composer:** roll-back to previous version format to support PHP8 as well ([5dd535e](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/5dd535e3cf6380493e8423902609b041cd6e256a))

## [7.1.9](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.8...v7.1.9) (2022-07-12)


### Bug Fixes

* **composer:** change the php version format to semantic one ([a73ec23](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/a73ec23d0e09718fd1150a1b78802133787eecc4))

## [7.1.8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.7...v7.1.8) (2022-07-12)


### Bug Fixes

* **composer:** update requirements to php >= 7.3.0 ([5eef7ed](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/5eef7ed174d0b2987958c72caacbead8ecf3583a))

## [7.1.7](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.6...v7.1.7) (2022-06-28)


### Bug Fixes

* **versioning:** patch release process configuration to get the version no. correctly updated ([2c27be7](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/2c27be7f1b18b8f0fef98d610c6eceeeedcf5cf7))

## [7.1.6](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.5...v7.1.6) (2022-06-28)


### Bug Fixes

* **customloggerclass:** patched docblock of CustomLoggerClass::log method ([2ef6f73](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/2ef6f73da18315e4a2fc3e2dc29f0b19b7a6d2d4))

## [7.1.5](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.4...v7.1.5) (2022-06-28)


### Bug Fixes

* **typings:** removed strict typings as we noticed issues under different platforms ([3a566d9](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/3a566d9a498329b12de6c3d08b41bac1d372e051))

## [7.1.4](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.3...v7.1.4) (2022-06-20)


### Bug Fixes

* **hexonet response:** fn addColumn patched type declaration issue ([06a0ad0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/06a0ad08df7d0b083d91eaed85a19b9f5ade43c0))

## [7.1.3](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.2...v7.1.3) (2022-06-10)


### Bug Fixes

* **ci:** upgraded npm engines, dev deps and reviewed release workflow ([1d242de](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/1d242de4887c869764e49990c5d74a9e3e676267))

## [7.1.2](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.1...v7.1.2) (2022-05-30)


### Bug Fixes

* **phpdoc:** change path to docs to /docs (github pages) ([e54648f](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/e54648fd5dcf34e5fa5b872f4d7fb2259885c497))

## [7.1.1](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.1.0...v7.1.1) (2022-05-23)


### Bug Fixes

* **phpstan:** increase phpStan level to 9 and fix reported issues ([a551b39](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/a551b39507869bed111f58500e94453ab779c704))

# [7.1.0](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.8...v7.1.0) (2022-04-20)


### Features

* **idn conversion:** added IDNConvert method to client allowing to explicitely convert domain names ([6774225](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/677422507b4f1446bf192532004ca55b5aa017a8))

## [7.0.8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.7...v7.0.8) (2022-04-12)


### Bug Fixes

* **high performance setup:** use the right protocol ([c524aa8](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/c524aa817e86193994d266e388e1a04b105d075d))

## [7.0.7](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.6...v7.0.7) (2022-04-07)


### Bug Fixes

* **auto idn conversion:** fixed to translate only idns ([d4ee814](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/d4ee814b7f69002c808a981afc192f13288f3b0a))

## [7.0.6](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.5...v7.0.6) (2022-03-28)


### Bug Fixes

* **curlopt_verbose:** disabled ([bc47cbd](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/bc47cbddc0417d4e829da62722bcb3e858202ca3))

## [7.0.5](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.4...v7.0.5) (2022-03-23)


### Bug Fixes

* **composer autoload:** rollback wrong psr-4 autoload change ([f04cf6e](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/f04cf6e8cd7fe402631218d26410d35a3168a495))

## [7.0.4](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.3...v7.0.4) (2022-03-23)


### Bug Fixes

* **composer autoload:** to include psr-4 config for HEXONET and RRPproxy as well ([fd5b2fd](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/fd5b2fd7df7d6e88ad3b9749b6631744b0e866b8))

## [7.0.3](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.2...v7.0.3) (2022-03-21)


### Bug Fixes

* **hexonet:** changed OT&E url (infrastructure improvement) ([acea748](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/acea74846432b00f3b6117c6308eb98de422f7e9))

## [7.0.2](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.1...v7.0.2) (2022-01-20)


### Bug Fixes

* **logger:** require custom logger to implement LoggerInterface ([ab73282](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/ab732821a4383a7b239eb76ee341b6556f2429c6))

## [7.0.1](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/compare/v7.0.0...v7.0.1) (2021-12-09)


### Bug Fixes

* **semantic-release:** added missing configuration file ([9972e93](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/9972e93018dfce08ddd95f679be8409fdab53010))

# 7.0.0 (2021-12-09)

### Features

- **revamped:** to support RRPproxy and HEXONET ([2c6381a](https://github.com/centralnicgroup-opensource/rtldev-middleware-php-sdk/commit/2c6381ae1886d35467cafdafb80829fbde8bc326))

### BREAKING CHANGES

- **revamped:** SocketConfig constructor now needs a parameter. APIClient got renamed to
  HexonetClient or RRPproxyClient. Removed function useDefaultConnect in
  favor of useOTESystem and useLIVESystem.

## [6.1.1](https://github.com/hexonet/php-sdk/compare/v6.1.0...v6.1.1) (2021-08-18)

### Bug Fixes

- **auto idn conversion:** added object classes ([acda2dc](https://github.com/hexonet/php-sdk/commit/acda2dc6bf485b0f596cb5a2187125ec7ce38716))

# [6.1.0](https://github.com/hexonet/php-sdk/compare/v6.0.8...v6.1.0) (2021-08-16)

### Features

- **auto idn conversion:** for Object related commands ([a855500](https://github.com/hexonet/php-sdk/commit/a8555004a012bbd2876e42cbcbf49c158c12540b))

## [6.0.8](https://github.com/hexonet/php-sdk/compare/v6.0.7...v6.0.8) (2021-06-15)

### Performance Improvements

- **array_push:** replaced with brackets notation ([7008d50](https://github.com/hexonet/php-sdk/commit/7008d5034903a931354c96d3653df748c5e610e8))
- **short array syntax:** usage reviewed ([c6b7261](https://github.com/hexonet/php-sdk/commit/c6b72613b1eebffe56783792bc62248263eb2423))

## [6.0.7](https://github.com/hexonet/php-sdk/compare/v6.0.6...v6.0.7) (2021-05-21)

### Bug Fixes

- **responsetranslator:** fixed regex which stripped out other returned properties ([2848c16](https://github.com/hexonet/php-sdk/commit/2848c1696a804a99a775d0d57e881afcba29a63d))

## [6.0.6](https://github.com/hexonet/php-sdk/compare/v6.0.5...v6.0.6) (2021-05-21)

### Bug Fixes

- **responsetranslator:** fixed preg_quote usage, added delimiter ([8988cdd](https://github.com/hexonet/php-sdk/commit/8988cdd1cb896693f145511e56a1eb5a979dc242))
- **responsetranslator:** reviewed regex and added translation case ([c513fe6](https://github.com/hexonet/php-sdk/commit/c513fe65391288779346bc2c33503f22b6142c1c))

## [6.0.5](https://github.com/hexonet/php-sdk/compare/v6.0.4...v6.0.5) (2021-05-21)

### Bug Fixes

- **responsetranslator:** fixed regular expression used ([e974948](https://github.com/hexonet/php-sdk/commit/e974948156495dbf61bb3f6a1959f39a899e24ff))

## [6.0.4](https://github.com/hexonet/php-sdk/compare/v6.0.3...v6.0.4) (2021-05-21)

### Bug Fixes

- **responsetranslator:** review generic replacement logic ([c76d2f6](https://github.com/hexonet/php-sdk/commit/c76d2f6e7119e3d689322c0c9cf1d5619bc099b4))

## [6.0.3](https://github.com/hexonet/php-sdk/compare/v6.0.2...v6.0.3) (2021-05-21)

### Bug Fixes

- **responsetranslator:** added templates for translating CheckDomainTransfer ([9f812db](https://github.com/hexonet/php-sdk/commit/9f812db89ba6e5e6ff13d017c9e514b89abc4007))

## [6.0.2](https://github.com/hexonet/php-sdk/compare/v6.0.1...v6.0.2) (2021-05-11)

### Bug Fixes

- **api docs:** cleanup build folder before generating the docs to avoid keeping deprecated files ([563266c](https://github.com/hexonet/php-sdk/commit/563266c89b9f69d4067955a12366da9dfd79bb4d))

## [6.0.1](https://github.com/hexonet/php-sdk/compare/v6.0.0...v6.0.1) (2021-04-06)

### Bug Fixes

- **response translator:** fix response translator regarding ACL response case ([4fa8ef8](https://github.com/hexonet/php-sdk/commit/4fa8ef8051973cfe02a700c98c66f7afd4114095))

# [6.0.0](https://github.com/hexonet/php-sdk/compare/v5.8.9...v6.0.0) (2021-04-06)

### Bug Fixes

- **response translator:** added missing typehints ([8b3acb7](https://github.com/hexonet/php-sdk/commit/8b3acb7aa83adc5a459413bd7e519b7360538103))

### Features

- **response translator:** added initial version coming with rewrite/restructuring from scratch ([8f19444](https://github.com/hexonet/php-sdk/commit/8f19444479cea5feac6d7fcd427b2dbb675c0bec))

### BREAKING CHANGES

- **response translator:** Downward incompatible restructuring (Merge of Response/ResponseTemplate, Rewrite ResponseTemplateManager) and introducing ResponseTranslator

## [5.8.9](https://github.com/hexonet/php-sdk/compare/v5.8.8...v5.8.9) (2021-01-21)

### Bug Fixes

- **ci:** exclude build/api-cache from @semantic-release/git assets ([11cfe91](https://github.com/hexonet/php-sdk/commit/11cfe91a4eae2964ec985d532eb3fea2f40c9774))

## [5.8.8](https://github.com/hexonet/php-sdk/compare/v5.8.7...v5.8.8) (2021-01-21)

### Bug Fixes

- **ci:** ignore tag commits ([dbda7ef](https://github.com/hexonet/php-sdk/commit/dbda7ef1b0759862840a340530208c1afbc87843))

## [5.8.7](https://github.com/hexonet/php-sdk/compare/v5.8.6...v5.8.7) (2021-01-21)

### Bug Fixes

- **ci:** add missing composer update for autoloader ([3cb0525](https://github.com/hexonet/php-sdk/commit/3cb05256e25b0b583dad433d243118f9b4957ada))
- **ci:** apt package installation reviewed ([a17e3ce](https://github.com/hexonet/php-sdk/commit/a17e3ce7cea9a51982c35c405d65afeb5defeef9))
- **ci:** fixed phpcs reported issues ([6b061d3](https://github.com/hexonet/php-sdk/commit/6b061d3eda807da09eb29f045d2f1892d9f6ae17))
- **ci:** migration from Travis CI to github actions ([e3d86b9](https://github.com/hexonet/php-sdk/commit/e3d86b99f24fcd68e78f3f576253ac32bebffc28))
- **phpdocumentor:** upgrade; review config; add to release step ([2ed94d0](https://github.com/hexonet/php-sdk/commit/2ed94d0963731827bebce9b224b4e380aba04c82))
- **responsetemplatemanager:** method \_\_wakeup must have public visibility ([9f3c7f1](https://github.com/hexonet/php-sdk/commit/9f3c7f19adcd17fd57bb8f97ef92aa82ff5204e9))

## [5.8.6](https://github.com/hexonet/php-sdk/compare/v5.8.5...v5.8.6) (2020-07-23)

### Bug Fixes

- **apiclient:** use php version without extra data ([a9efa97](https://github.com/hexonet/php-sdk/commit/a9efa971302e4dbce449045917427477068e3546))

## [5.8.5](https://github.com/hexonet/php-sdk/compare/v5.8.4...v5.8.5) (2020-07-17)

### Bug Fixes

- **apiclient:** fixed log method call to use correct argument type ([0ba6cdd](https://github.com/hexonet/php-sdk/commit/0ba6cdd87c601153ad0e91e099ea293ccb6cd6ef))

## [5.8.4](https://github.com/hexonet/php-sdk/compare/v5.8.3...v5.8.4) (2020-07-15)

### Bug Fixes

- **apiclient:** fixed types and a nasty return+else ([7492c9f](https://github.com/hexonet/php-sdk/commit/7492c9fb59b5ab4367a82a8330fd0654151e2401))

## [5.8.3](https://github.com/hexonet/php-sdk/compare/v5.8.2...v5.8.3) (2020-04-27)

### Bug Fixes

- **apiclient:** remove deprecated private method toUpperCaseKeys ([bbab21e](https://github.com/hexonet/php-sdk/commit/bbab21ec869d87ab5ffbde5ee0590a3c4ac8e233))

## [5.8.2](https://github.com/hexonet/php-sdk/compare/v5.8.1...v5.8.2) (2020-04-15)

### Bug Fixes

- **apiclient:** fixed automatic idn conversion ([aae4fe6](https://github.com/hexonet/php-sdk/commit/aae4fe68e4c63485d1023c014af03c5862f25f50))

## [5.8.1](https://github.com/hexonet/php-sdk/compare/v5.8.0...v5.8.1) (2020-04-09)

### Bug Fixes

- **security:** fixed password replace mechanism ([f22ab11](https://github.com/hexonet/php-sdk/commit/f22ab113019c3da571fac769e0388d7c83f56f15))

# [5.8.0](https://github.com/hexonet/php-sdk/compare/v5.7.0...v5.8.0) (2020-04-06)

### Features

- **phar:** create and upload phar/phar.gz archives in release process ([696d4a8](https://github.com/hexonet/php-sdk/commit/696d4a871c16675132b49e05f5fb26e4a900ad60))

# [5.7.0](https://github.com/hexonet/php-sdk/compare/v5.6.1...v5.7.0) (2020-04-03)

### Features

- **apiclient:** allow to specify additional libraries via setUserAgent ([7f9cf7c](https://github.com/hexonet/php-sdk/commit/7f9cf7c79a0400eb0c7a03176c8946eea4a2c13a))

## [5.6.1](https://github.com/hexonet/php-sdk/compare/v5.6.0...v5.6.1) (2020-04-02)

### Bug Fixes

- **security:** replace passwords whereever they could be used for output ([9e97123](https://github.com/hexonet/php-sdk/commit/9e9712315697e513860474fe01d02730a68666f7))

# [5.6.0](https://github.com/hexonet/php-sdk/compare/v5.5.1...v5.6.0) (2020-04-02)

### Features

- **response:** added getCommandPlain (getting used command in plain text) ([c3992a4](https://github.com/hexonet/php-sdk/commit/c3992a48aa9e83c4eeeff3fff34cee018ef20ef5))

## [5.5.1](https://github.com/hexonet/php-sdk/compare/v5.5.0...v5.5.1) (2020-04-02)

### Bug Fixes

- **namespace:** review namespace usages ([509e988](https://github.com/hexonet/php-sdk/commit/509e9882e8412c4097ca988668383194b53a8537))

# [5.5.0](https://github.com/hexonet/php-sdk/compare/v5.4.2...v5.5.0) (2020-04-01)

### Features

- **logger:** possibility to override debug mode's default logging mechanism. See README.md ([680c70e](https://github.com/hexonet/php-sdk/commit/680c70e888b2d8ce9cdac03530954c0c379ef04c))

## [5.4.2](https://github.com/hexonet/php-sdk/compare/v5.4.1...v5.4.2) (2020-04-01)

### Bug Fixes

- **auto-versioning:** fixed broken version auto-update process ([58103b9](https://github.com/hexonet/php-sdk/commit/58103b95435dc7822df516aa49e6491fd2d545d2))

## [5.4.1](https://github.com/hexonet/php-sdk/compare/v5.4.0...v5.4.1) (2020-04-01)

### Bug Fixes

- **messaging:** return a specific error template in case code or description are missing ([59119c3](https://github.com/hexonet/php-sdk/commit/59119c31f16a269e24b07609f5ee4364628e3321))

# [5.4.0](https://github.com/hexonet/php-sdk/compare/v5.3.0...v5.4.0) (2020-04-01)

### Bug Fixes

- **response:** fixed placeholder replacements ([4e188dd](https://github.com/hexonet/php-sdk/commit/4e188dd393f57a0013a8512b482d1a3d96683324))

### Features

- **response:** possibility of placeholder vars in standard responses to improve error details ([1d0a017](https://github.com/hexonet/php-sdk/commit/1d0a0170134232f9a83a006dd8979cee9f3d0d4b))

# [5.3.0](https://github.com/hexonet/php-sdk/compare/v5.2.0...v5.3.0) (2020-03-31)

### Features

- **apiclient:** support the `High Performance Proxy Setup`. see README.md ([90c73ab](https://github.com/hexonet/php-sdk/commit/90c73ab84225a29a1a17fb6fa346bf22932de121))

# [5.2.0](https://github.com/hexonet/php-sdk/compare/v5.1.0...v5.2.0) (2020-03-31)

### Features

- **apiclient:** automatic IDN conversion of API command parameters to punycode ([79936b0](https://github.com/hexonet/php-sdk/commit/79936b0f9e49cc56e839657e1af30d76d78aa60a))

# [5.1.0](https://github.com/hexonet/php-sdk/compare/v5.0.1...v5.1.0) (2020-03-13)

### Features

- **apiclient:** support bulk parameters through nested array in API command ([5494a41](https://github.com/hexonet/php-sdk/commit/5494a41516eda545e0605663671dbcea95a8d848))

## [5.0.1](https://github.com/hexonet/php-sdk/compare/v5.0.0...v5.0.1) (2020-01-22)

### Bug Fixes

- **composer:** cleanup; trigger new release for commit 99b5b35 ([0b63c3f](https://github.com/hexonet/php-sdk/commit/0b63c3f92669ae7575bf7dfb65e87fee3e3b3f53))

# [5.0.0](https://github.com/hexonet/php-sdk/compare/v4.5.5...v5.0.0) (2020-01-22)

### Code Refactoring

- **php5 support:** review to still support PHP5; for refactoring our 3rd party integrations ([cd652d5](https://github.com/hexonet/php-sdk/commit/cd652d57de70dfdc88402dfbbe19848b5ca25446))

### BREAKING CHANGES

- **php5 support:** APIClient's method requestNextResponsePage now throws an Exception instead of an
  Error (PHP5 compatibility). We will review in future in direction of PHP7 only.

## [4.5.5](https://github.com/hexonet/php-sdk/compare/v4.5.4...v4.5.5) (2019-10-04)

### Bug Fixes

- **responsetemplate/mgr:** improve description of `423 Empty API response` ([63d6c4c](https://github.com/hexonet/php-sdk/commit/63d6c4c))

## [4.5.4](https://github.com/hexonet/php-sdk/compare/v4.5.3...v4.5.4) (2019-09-18)

### Bug Fixes

- **npm:** review package.json ([2684e71](https://github.com/hexonet/php-sdk/commit/2684e71))

## [4.5.3](https://github.com/hexonet/php-sdk/compare/v4.5.2...v4.5.3) (2019-09-18)

### Bug Fixes

- **release process:** fix path to composer in travis ([cc3f453](https://github.com/hexonet/php-sdk/commit/cc3f453))
- **release process:** review configuration ([6fa9481](https://github.com/hexonet/php-sdk/commit/6fa9481))

## [4.5.2](https://github.com/hexonet/php-sdk/compare/v4.5.1...v4.5.2) (2019-08-16)

### Bug Fixes

- **APIClient:** change default SDK url ([c5505fe](https://github.com/hexonet/php-sdk/commit/c5505fe))

## [4.5.1](https://github.com/hexonet/php-sdk/compare/v4.5.0...v4.5.1) (2019-06-14)

### Bug Fixes

- **APIClient:** fix typo in method call ([c932484](https://github.com/hexonet/php-sdk/commit/c932484))

# [4.5.0](https://github.com/hexonet/php-sdk/compare/v4.4.1...v4.5.0) (2019-04-16)

### Features

- **responsetemplate:** add isPending method ([f12c64f](https://github.com/hexonet/php-sdk/commit/f12c64f))

## [4.4.1](https://github.com/hexonet/php-sdk/compare/v4.4.0...v4.4.1) (2019-04-04)

### Bug Fixes

- **APIClient:** return apiclient instance in setUserAgent method ([ec469ab](https://github.com/hexonet/php-sdk/commit/ec469ab))

# [4.4.0](https://github.com/hexonet/php-sdk/compare/v4.3.1...v4.4.0) (2019-04-01)

### Features

- **apiclient:** review user-agent header usage ([6aa5342](https://github.com/hexonet/php-sdk/commit/6aa5342))

## [4.3.1](https://github.com/hexonet/php-sdk/compare/v4.3.0...v4.3.1) (2018-10-24)

### Bug Fixes

- **phpDocumentor:** install missing dep graphviz ([52252af](https://github.com/hexonet/php-sdk/commit/52252af))

# [4.3.0](https://github.com/hexonet/php-sdk/compare/v4.2.0...v4.3.0) (2018-10-24)

# [4.2.0](https://github.com/hexonet/php-sdk/compare/v4.1.0...v4.2.0) (2018-10-17)

### Bug Fixes

- **dependabot:** minor release on build commit msg ([900fc97](https://github.com/hexonet/php-sdk/commit/900fc97))

# [4.1.0](https://github.com/hexonet/php-sdk/compare/v4.0.4...v4.1.0) (2018-10-15)

### Features

- **client:** add method getSession ([fae89c0](https://github.com/hexonet/php-sdk/commit/fae89c0))

## [4.0.4](https://github.com/hexonet/php-sdk/compare/v4.0.3...v4.0.4) (2018-10-05)

### Bug Fixes

- **docs:** review jsdoc comments ([0d8f5d7](https://github.com/hexonet/php-sdk/commit/0d8f5d7))

## [4.0.3](https://github.com/hexonet/php-sdk/compare/v4.0.2...v4.0.3) (2018-10-05)

### Bug Fixes

- **docs:** fix class docs and ([c1a4e5e](https://github.com/hexonet/php-sdk/commit/c1a4e5e))

## [4.0.1](https://github.com/hexonet/php-sdk/compare/v4.0.0...v4.0.1) (2018-10-05)

### Bug Fixes

- **composer:** consider ResponseParser namespace for autload ([032aac3](https://github.com/hexonet/php-sdk/commit/032aac3))

# [4.0.0](https://github.com/hexonet/php-sdk/compare/v3.0.3...v4.0.0) (2018-10-05)

### Bug Fixes

- **travis:** try config review ([dfdc6cd](https://github.com/hexonet/php-sdk/commit/dfdc6cd))
- **travis:** try release process review ([c7cee36](https://github.com/hexonet/php-sdk/commit/c7cee36))

### Features

- **4.0.0:** Merge pull request [#2](https://github.com/hexonet/php-sdk/issues/2) from hexonet/v4.0.0 ([55d095c](https://github.com/hexonet/php-sdk/commit/55d095c))

### BREAKING CHANGES

- **4.0.0:** Review in direction of our generic UML Diagram.

### Changelog

All notable changes to this project will be documented in this file. Dates are displayed in UTC.

#### [v3.0.3](https://github.com/hexonet/php-sdk/compare/v3.0.2...v3.0.3) (3 July 2018)

- added api documentation [`034c492`](https://github.com/hexonet/php-sdk/commit/034c492e5c9f29084207fc0a0c657451b570cafc)
- Update README.md [`8def453`](https://github.com/hexonet/php-sdk/commit/8def453f8ccda2e807574ec47e955b3489355915)

#### [v3.0.2](https://github.com/hexonet/php-sdk/compare/v3.0.1...v3.0.2) (2 July 2018)

- added changelog generator [`896848e`](https://github.com/hexonet/php-sdk/commit/896848e8ab67a88f8cd86647ca0f1c8cfe342672)

#### [v3.0.1](https://github.com/hexonet/php-sdk/compare/v3.0.0...v3.0.1) (2 July 2018)

- readme: add slack chat badge [`b58e774`](https://github.com/hexonet/php-sdk/commit/b58e774943bc9f85e5bbce863fcd8140ca5be9b6)
- readme: fix getting started [`e8ec52c`](https://github.com/hexonet/php-sdk/commit/e8ec52c3060e7c24a6ed89436f1fcdb38373c7ef)
- readme: added packagist module version badge [`76c2f49`](https://github.com/hexonet/php-sdk/commit/76c2f49a59c817406ea1d61d13577d688c867628)
- readme: add license and contributing badge [`91b0559`](https://github.com/hexonet/php-sdk/commit/91b05594504f443f1ab8942e5540a59b3c762fca)
- readme: added php version badge [`729e8b2`](https://github.com/hexonet/php-sdk/commit/729e8b2bc3937d0480eeab0bf9b5e5f7586414c4)

### [v3.0.0](https://github.com/hexonet/php-sdk/compare/v2.0.3...v3.0.0) (21 June 2018)

- moved connect method as static method to connection class [`c34761f`](https://github.com/hexonet/php-sdk/commit/c34761f88e9f44c755cf128c7d9aec8cb195d3ea)

#### [v2.0.3](https://github.com/hexonet/php-sdk/compare/v2.0.2...v2.0.3) (21 June 2018)

- remove composer.lock from .gitignore [`d2c6368`](https://github.com/hexonet/php-sdk/commit/d2c636805bdb25336a8552654dfcc83d417e9396)

#### [v2.0.2](https://github.com/hexonet/php-sdk/compare/v2.0.1...v2.0.2) (21 June 2018)

- added class map to composer.json [`0bd34e0`](https://github.com/hexonet/php-sdk/commit/0bd34e039e77b65fa51b6da8412b69c3f9f2e1e5)

### [v2.0.1](https://github.com/hexonet/php-sdk/compare/v1.0.0...v2.0.1) (21 June 2018)

- added phpDocumentor [`4175b9d`](https://github.com/hexonet/php-sdk/commit/4175b9d8c258bcf1337a689669c01c84db0651fc)

#### v1.0.0 (21 June 2018)

- added unit tests [`b6cedd4`](https://github.com/hexonet/php-sdk/commit/b6cedd4c9c8ac5bc491ab10662491024bdfe4847)
- added unit tests for class connection and response [`1b3bfbb`](https://github.com/hexonet/php-sdk/commit/1b3bfbb8a0fb6852b690bb61d1954a3febbc0488)
- minor cleanup [`777b1ce`](https://github.com/hexonet/php-sdk/commit/777b1cec613f46ed06e93283e4c502ba646dd439)
- setup phpunit; phpcs; phpcsf [`f88965b`](https://github.com/hexonet/php-sdk/commit/f88965b668140b8c2e9434df7581466b27b7c47f)
- updated readme [`f6cfcae`](https://github.com/hexonet/php-sdk/commit/f6cfcaef78be59fd494e1ab2aaa94d8f9ae4d766)
- update readme [`f7e01ea`](https://github.com/hexonet/php-sdk/commit/f7e01ea92bcf013bd843bf163c63ce8250001020)
- added .gitignore and phpunit.xml [`79d3127`](https://github.com/hexonet/php-sdk/commit/79d31277de5c188167062c20c4a963a02c6d197f)
- updated readme.md [`2b3cda3`](https://github.com/hexonet/php-sdk/commit/2b3cda3d8fcd6ce73adc32b8a33d847614f954cd)
- reviewed repository structure; added basic files [`7fdd40c`](https://github.com/hexonet/php-sdk/commit/7fdd40c0bcbd8b1b6789b1b62d2690b2e34b674a)
- initial release [`433b056`](https://github.com/hexonet/php-sdk/commit/433b0560f65f8053bc07118c6fa810cccf44d9e2)
