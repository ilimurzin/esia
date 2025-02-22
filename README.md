# esia

[![Tests Status](https://github.com/ilimurzin/esia/actions/workflows/tests.yml/badge.svg)](https://github.com/ilimurzin/esia/actions/workflows/tests.yml)

Форк пакета [fr05t1k/esia](https://github.com/fr05t1k/esia).

## Отличия

1. Возможность передать стейт и дополнительные параметры (например, person_filter) в метод формирования ссылки.
2. Сигнеры КриптоПро (через [приложение командной строки](https://www.cryptopro.ru/products/other/cryptcp) или через [расширение для PHP](https://github.com/CryptoPro/phpcades)).
3. Метод для получения ролей пользователя.
4. Метод для получения маркера доступа в обмен на маркер обновления.
5. Метод для получения маркера доступа на основе полномочий системы-клиента.
6. Метод для получения организаций пользователя.

## Установка

```sh
composer require ilimurzin/esia
```

## Совместимость

Пакет версии 3.x совместим с [fr05t1k/esia](https://github.com/fr05t1k/esia) 2.4.

Для перехода на форк можно выполнить команды:

```sh
composer remove fr05t1k/esia
composer require ilimurzin/esia:^3.2
```

При этом уже написанный код не сломается.
