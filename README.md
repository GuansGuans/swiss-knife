# swiss-knife

> [!NOTE]
> Swiss knife of everyone. - 每个人的神兵利器

[![Cron job status](https://api.cron-job.org/jobs/8682619/e7904d8ec2bfde3c/status-7.svg)](https://console.cron-job.org/dashboard)
[![mirror](https://github.com/GuansGuans/swiss-knife/actions/workflows/mirror.yml/badge.svg)](https://github.com/GuansGuans/swiss-knife/actions/workflows/mirror.yml)
[![tests](https://github.com/GuansGuans/swiss-knife/actions/workflows/tests.yml/badge.svg)](https://github.com/GuansGuans/swiss-knife/actions/workflows/tests.yml)
[![php-cs-fixer](https://github.com/GuansGuans/swiss-knife/actions/workflows/php-cs-fixer.yml/badge.svg)](https://github.com/GuansGuans/swiss-knife/actions/workflows/php-cs-fixer.yml)
[![codecov](https://codecov.io/gh/GuansGuans/swiss-knife/graph/badge.svg?token=Z6FCGRFN6B)](https://codecov.io/gh/GuansGuans/swiss-knife)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/GuansGuans/swiss-knife)](https://github.com/GuansGuans/swiss-knife/releases)
[![GitHub Tag](https://img.shields.io/github/v/tag/GuansGuans/swiss-knife)](https://github.com/GuansGuans/swiss-knife/tags)
[![GitHub License](https://img.shields.io/github/license/GuansGuans/swiss-knife)](https://github.com/GuansGuans/swiss-knife/blob/main/LICENSE)

## Requirement

* PHP >= 8.5
* Composer >= 2.0

## Installation

```shell
git clone ...
cd swiss-knife/
composer install --no-dev --ansi -v
# 配置相关环境变量...
composer app:install
```

## Usage

## Composer scripts

```shell
composer checks:artisan
composer checks:optional
composer checks:required
composer php-cs-fixer:fix
composer ecs:fix
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

* [GuansGuans](https://github.com/GuansGuans)
* [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
