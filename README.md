# esia

[![Tests Status](https://github.com/ilimurzin/esia/actions/workflows/tests.yml/badge.svg)](https://github.com/ilimurzin/esia/actions/workflows/tests.yml)

Пакет для [входа через Госуслуги](https://partners.gosuslugi.ru/catalog/esia).
Поддерживается в двух версиях: 3.x и 4.x.
Пакет версии 3.x обратно совместим с [fr05t1k/esia](https://github.com/fr05t1k/esia) 2.4.
Пакет версии 4.x несовместим с предыдущими версиями, но поддерживает новые эндпоинты ЕСИА: v2/ac и v3/te.

## 4.x

### Установка

```sh
composer require ilimurzin/esia
```

### Использование

Пакет использует модель контроля на основе делегированного принятия решения.
Подробнее в [методических рекомендациях](https://digital.gov.ru/ru/documents/6186/).

```php
$config = new \Esia\Config(
    clientId: 'FJ-VOLGA',
    clientCertificateHash: 'CD6EA35843FDE0212F301509EDD5B51BA7C954782FA4DE0608550A7FB35D80EE',
    redirectUrl: 'http://localhost/response.php',
    portalUrl: 'https://esia-portal1.test.gosuslugi.ru/',
    scopes: ['fullname', 'email'],
);

$esia = new \Esia\Esia(
    $config,
    new \Esia\Signer\CliCryptoProSigner(
        'HDIMAGE\\\\3f452f01.000\\241C',
        '1234567890',
        '/opt/cprocsp/bin/csptest'
    )
);

// state нужно сгенерировать и сохранить в сессию
$state = '7648d7bd-6369-4073-b545-90250f68025e';

$url = $esia->buildUrl($state);

// Пользователь переходит по url

// После авторизации ЕСИА возвращает пользователя на redirectUrl с параметрами code и state
// state нужно сравнить со значением, сохраненным в сессию

// Получение маркера доступа в обмен на авторизационный код
$token = $esia->getToken($code);

// Пакет предоставляет только методы для получения маркера доступа.
// Запрос за данными пользователя при необходимости можно сформировать самостоятельно.
// Полученный маркер доступа передается в запросы за данными в заголовке Authorization, пример:
// $request->withHeader('Authorization', 'Bearer ' . $token->accessToken);

// Для запроса за данными пользователя также потребуется oid
$oid = $token->getOid();

// Получение нового маркера доступа в обмен на маркер обновления
$token = $esia->refreshToken($token->refreshToken);
```

### Отличия от 2.4

1. Поддержка новых эндпоинтов ЕСИА: v2/ac и v3/te.
2. Все объекты иммутабельные, метод получения токена возвращает объект со всеми данными, а не только маркер доступа.
3. Обязательная передача стейта в метод формирования ссылки и возможность передать дополнительные параметры (например, person_filter).
4. Сигнеры КриптоПро: через приложение командной строки `csptest` или через [расширение для PHP](https://github.com/CryptoPro/phpcades).
5. Отсутствуют методы для получения данных пользователя.
6. Метод для получения маркера доступа в обмен на маркер обновления.

## 3.x

### Отличия от 2.4

1. Возможность передать стейт и дополнительные параметры (например, person_filter) в метод формирования ссылки.
2. Сигнеры КриптоПро (через [приложение командной строки](https://www.cryptopro.ru/products/other/cryptcp) или через [расширение для PHP](https://github.com/CryptoPro/phpcades)).
3. Метод для получения ролей пользователя.
4. Метод для получения маркера доступа в обмен на маркер обновления.
5. Метод для получения маркера доступа на основе полномочий системы-клиента.
6. Метод для получения организаций пользователя.

### Совместимость

Пакет версии 3.x совместим с [fr05t1k/esia](https://github.com/fr05t1k/esia) 2.4.

Для перехода на форк можно выполнить команды:

```sh
composer remove fr05t1k/esia
composer require ilimurzin/esia:^3.2
```

При этом уже написанный код не сломается.
