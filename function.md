##  php artisan 
php artisan make:model Fight -fcsm


##  psalm 
vendor\bin\psalm --alter --issues=MissingReturnType --dry-run

vendor/bin/psalm --set-baseline=your-baseline.xml

vendor/bin/psalm --update-baseline

##  larastan
composer require --dev nunomaduro/larastan



## ide helper 
"barryvdh/laravel-ide-helper": "dev-master"
Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class,
composer require "doctrine/dbal: ~2.3"

php artisan ide-helper:generate - 为 Facades 生成注释
_php artisan ide-helper:models_ - 为数据模型生成注释
php artisan ide-helper:meta - 生成 PhpStorm Meta file

