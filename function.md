##  php artisan 
php artisan make:model Fight -fcsm


##  psalm 
vendor\bin\psalm --alter --issues=MissingReturnType --dry-run

vendor/bin/psalm --set-baseline=your-baseline.xml

vendor/bin/psalm --update-baseline
