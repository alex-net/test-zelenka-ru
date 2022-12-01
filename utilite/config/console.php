<?php

return [
    'id' => 'Zelenka.ru console utilite',
    'basePath' => dirname(__DIR__),
    'extensions' => require __DIR__ . '/../vendor/yiisoft/extensions.php',
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'mysql:host=db;dbname=zelenka_ru',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
        ],
    ],
];