# yii2-queue
Yii 2.0 Queue Extension

Non blocking queue manager for Yii 2.0

#####Install:
`php composer.phar require diretasistemas/yii2-queue "*"`

#####Config:
```php
'components' => [
    'queue' => [
        'class' => \yii\queue\components\QueueComponent::className(),
        'queueName' => 'default-queue',
        'timeout' => 50, // optional
        'workers' => [
            'test' => [
                'class' => \yii\queue\components\WorkerComponent::className(),
                'action' => [
                    'class' => \console\controllers\TestController::className(),
                ]
            ],
            ...
        ],
        'channels' => [
            'default' => [
                'class' => \yii\queue\components\ChannelComponent::className(),
                    'driver' => [
                        'class' => \yii\queue\drivers\MysqlConnection::className(),
                        'connection' => 'db'
                    ]
                ]
            ],
            ...
        ]
    ]
]
```

Before use apply migrations:
```php
./yii migrate/up --migrationPath=@vendor/argayash/yii2-queue/migrations
```

###Usage:

#### Push message to queue:
```php
Yii::$app->queue->getChannel('default')->push(
    new MessageModel([
        'worker' => 'test',
        'method' => 'actionSayHello',
        'arguments' => [
            'say' => 'hello!'
        ]
    ])
);
```

#### Run queue worker daemon (console app):

```php
\Yii::$app->queue->startDaemon();
```        


