# Associate users with permissions and roles


This package allows you to manage user permissions and roles in a database.

fork from: [spatie/laravel-permission](https://github.com/spatie/laravel-permission)

Once installed you can do stuff like this:

```php
// Adding permissions to a user
$user->givePermissionTo('edit articles');

// Adding permissions via a role
$user->assignRole('writer');

$role->givePermissionTo('edit articles');
```

If you're using multiple guards we've got you covered as well. Every guard will have its own set of permissions and roles that can be assigned to the guard's users. Read about it in the [using multiple guards](#using-multiple-guards) section of the readme.

Because all permissions will be registered on [Laravel's gate](https://laravel.com/docs/5.5/authorization), you can check if a user has a permission with Laravel's default `can` function:

```php
$user->can('edit articles');
```

## Documentation, Installation, and Usage Instructions

#### Add package into file composer.json
```php
Add repositories
"repositories": [
    {
        "type":"package",
        "package": {
            "name": "hongthach12/laravel-permission",
            "version": "v2.1",
            "source": {
                "url": "git@github.com:hongthach12/laravel-permission.git",
                "type": "git",
                "reference": "v2.1"
            }
        }
    }
]
```
Add require
```php
"require" : {
    ...
    "hongthach12/laravel-permission": "v2.1"
}
```
Add to autoload psr-4
```php
"Spatie\\Permission\\": "vendor/hongthach12/laravel-permission/src/"
```
Add to autoload files
```php
"vendor/hongthach12/laravel-permission/src/helpers.php"
```

#### Lumen install
Copy config file and migration
```php
cp vendor/hongthach12/laravel-permission/config/permission.php config/permission.php
cp vendor/hongthach12/laravel-permission/database/migrations/create_permission_tables.php.stub database/migrations/2020_01_01_000000_create_permission_tables.php
```

Add the service provider in your `bootstrap/app.php` file
```php
$app->configure('permission');
$app->alias('cache', \Illuminate\Cache\CacheManager::class);  // if you don't have this already
$app->register(Spatie\Permission\PermissionServiceProvider::class);
```

#### Laravel install



Add the service provider in your config/app.php file
```php
'providers' => [
    // ...
    Spatie\Permission\PermissionServiceProvider::class,
];
```
You can publish the migration with:
```php
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
```
You can publish the config file with:
```php
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="config"
```
After the migration has been published you can create the role- and permission-tables by running the migrations:
```php
php artisan migrate
```
	
## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
