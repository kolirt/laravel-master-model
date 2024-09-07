# Laravel Master Model

Laravel Master Model is a powerful package for the Laravel framework that simplifies working with models, particularly
in **saving relationships** and **uploading files**.

This package is designed for developers who want to optimize the process of working with databases and files, reducing
code complexity and enhancing performance.

## Structure

- [Getting started](#getting-started)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Setup](#setup)
- [Console commands](#console-commands)
- [Use cases](#use-cases)
  - [File saving](#file-saving)
  - [Saving `HasOne` relationship](#saving-hasone-relationship)
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

### File saving

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


### Saving `HasOne` relationship

```php
class Item extends Model
{
    use MasterModel;

    protected $fillable = [
        'image',
    ];
    
    public function phone(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ItemPhone::class);
    }
}
```
You can **save** `HasOne` relationship in the same way as a file. If relationship exists, it will be updated, otherwise it will be created

```php
class ExampleController extends Controller
{
    public function index(Request $request, $id)
    {
        $item = Item::query()->findOrFail($id);
        $item->update([
            'phone' => [
                'number' => '1234567890'
            ]
        ]);
    }
}
```

You can also **delete** the relationship by setting it to `null`

```php
class ExampleController extends Controller
{
    public function index(Request $request, $id)
    {
        $item = Item::query()->findOrFail($id);
        $item->update([
            'phone' => null
        ]);
    }
}
```


## FAQ

Check closed [issues](https://github.com/kolirt/laravel-master-model/issues) to get answers for most asked questions

## License

[MIT](LICENSE.txt)

## Other packages

Check out my other packages on my [GitHub profile](https://github.com/kolirt)
