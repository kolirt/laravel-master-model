# Laravel Master Model
Laravel Master Model is a powerful package for the Laravel framework that simplifies working with models, particularly
in **saving relations** and **uploading files**.

This package is designed for developers who want to optimize the process of working with databases and files, reducing
code complexity and enhancing performance.


## Structure
- [Getting started](#getting-started)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Setup](#setup)
- [Console commands](#console-commands)
- [Use cases](#use-cases)
  - [Saving files](#saving-files)
  - [Saving file from HTTP request](#saving-file-from-http-request)
  - [Deleting files](#deleting-files)
  - [Saving `HasOne`, `MorphOne` relations](#saving-hasone-morphone-relations)
  - [Saving `HasMany`, `MorphMany` relations](#saving-hasmany-morphmany-relations)
  - [Saving `HasMany`, `MorphMany` relations with `sync` mode](#saving-hasmany-morphmany-relations-with-sync-mode)
  - [Saving `BelongsToMany` relation](#saving-belongstomany-relation)
  - [Saving `BelongsToMany` relation with `sync` mode](#saving-belongstomany-relation-with-sync-mode)
  - [Response file](#response-file)
- [FAQ](#faq)
- [License](#license)
- [Other packages](#other-packages)

<a href="https://www.buymeacoffee.com/kolirt" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/arial-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" >
</a>


## Getting started

### Requirements
- PHP >= 8.1
- Laravel >= 10


### Installation
```bash
composer require kolirt/laravel-master-model
```


### Setup
Publish config file

```bash
php artisan master-model:install
```

Use the `MasterModel` trait in your models

```php
use Kolirt\MasterModel\MasterModel;

class Item extends Model
{
    use MasterModel;
}
```


## Console commands
- `master-model:install` - Install master model package
- `master-model:publish-config` - Publish the config file


## Use cases

### Saving files
```php
class Item extends Model
{
    use MasterModel;

    protected $fillable = [
        'image',
    ];
}
```

MasterModel automatically saves the file and deletes the old file, if it existed

```php

class ExampleController extends Controller
{
    public function index(Request $request, $id)
    {
        $data = $request->validate([
            'image' => 'required|file',
        ]);

        $item = Item::query()->findOrFail($id);
        $item->update($data);
    }
}
```

You can specify folder and disk for each file

```php
class Item extends Model
{
    use MasterModel;

    protected $fillable = [
        'image',
    ];
    
    protected string $upload_model_folder = 'items';

    protected array $upload_folders = [
        'image' => 'image',
    ];

    protected array $upload_disks = [
        'image' => 'public'
    ];
}
```


### Saving files from third-party resources
You no longer need to worry about saving files from third-party resources, just save the `response` and MasterModel will save everything for you

```php
class ExampleController extends Controller
{
    public function index($id)
    {
        $file1_url = 'https://png.pngtree.com/png-clipart/20230126/original/pngtree-fresh-red-apple-png-image_8930987.png';
        $response = \Illuminate\Support\Facades\Http::get($file1_url);

        $file2_url = 'https://cubanvr.com/wp-content/uploads/2023/07/ai-image-generators.webp';
        $client = new \GuzzleHttp\Client();
        $response2 = $client->get($file2_url);

        Item::create([
            'image' => $response,
            'image2' => $response2
        ]);
    }
}
```


### Deleting files
You can delete files by setting the field to `null`

```php
$item = Item::query()->first();

$item->update([
    'image' => null
]);
```

To have files deleted automatically, delete data through the model, not through the builder, and don't forget to load the necessary relations in which you want to delete files

_If there are files in the relationship and the relationship is deleted not through the model, the files won't be deleted and will clog up storage_

```php
$item = Item::query()->with(['phone', 'addresses'])->first();
/**
* All files in the model and in the loaded relations will be deleted
 */
$item->delete();
```


### Saving `HasOne`, `MorphOne` relations
You can **save** `HasOne`, `MorphOne` relations in the same way as a file. If relation exists, it will be updated, otherwise it will be created

```php
$item = Item::query()->first();

$item->update([
    'phone' => [ // hasOne, morphOne relation
        'number' => '1234567890'
    ]
]);
```

You can also **delete** the relation by setting it to `null`

```php
$item = Item::query()->first();

$item->update([
    'phone' => null // hasOne, morphOne relation
]);
```


### Saving `HasMany`, `MorphMany`  relations
You can **save** `HasMany`, `MorphMany` relations in the same way as a file. If relations exists, it will be updated, otherwise it will be created

```php
$item = Item::query()->first();

$item->update([
    'phones' => [ // hasMany, morphMany relations
        [ // will be created
            'number' => '1234567890'
        ],
        [ // will be updated (id = 1)
            'id' => 1,
            'number' => '0987654321'
        ]
    ]
]);
```


### Saving `HasMany`, `MorphMany` relations with `sync` mode
You can also **sync** `HasMany`, `MorphMany` relations. Unspecified relations will be deleted

```php
$item = Item::query()->first();

$item->update([
    'phones' => [ // hasMany, morphMany relations
        'mode' => 'sync', // not specified relations will be deleted
        'value' => [
            [ // will be created
                'number' => '1234567890'
            ],
            [ // will be updated (id = 1)
                'id' => 1,
                'number' => '0987654321'
            ]
        ]
    ]
]);
```


### Saving `BelongsToMany` relation

```php
$item = Item::query()->first();

$item->update([
    'categories' => [1, 2, 3] // belongsToMany relations
]);

$item->update([
    'categories' => [ // belongsToMany relation
        1 => ['name' => 'Category 1'], 
        2 => ['name' => 'Category 2'], 
        3 => ['name' => 'Category 3']
    ]
]);
```


### Saving `BelongsToMany` relation with `sync` mode
You can **sync** the `BelongsToMany` relation. Everything that is not specified when saving will be deleted

```php
$item = Item::query()->first();

$item->update([
    'categories' => [ // belongsToMany relation
        'mode' => 'sync', // not specified relations will be deleted
        'value' => [1, 2, 3]
    ]
]);

$item->update([
    'categories' => [ // belongsToMany relation
        'mode' => 'sync', // not specified relations will be deleted
        'value' => [
            1 => ['name' => 'Category 1'], 
            2 => ['name' => 'Category 2'], 
            3 => ['name' => 'Category 3']
        ]
    ]
]);
```


### Response file
Use the `responseFile` method to return a file in a controller

```php
class FileController extends Controller
{

    public function index()
    {
        $item = Item::query()->first();
        return $item->responseFile('image');
    }

}
```


## FAQ
Check closed [issues](https://github.com/kolirt/laravel-master-model/issues) to get answers for most asked questions


## License
[MIT](LICENSE.txt)


## Other packages
Check out my other packages on my [GitHub profile](https://github.com/kolirt)
