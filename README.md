# Laravel Master Model 1.0.0

Package will help for easing save relations and upload images.

## Installation

```
composer require kolirt/laravel-master-model
```

## Usage

You need to extend your model from `Kolirt\MasterModel\Model`.

## Example

Provider.php

```php
<?php

namespace App\Models;

use Kolirt\MasterModel\Model;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'city',
        'address',
        'postcode',
        'image',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function agents()
    {
        return $this->hasMany(ProviderContact::class);
    }
}
```

ProviderContact.php

```php
<?php

namespace App\Models;

use Kolirt\MasterModel\Model;

class ProviderContact extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'position',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
```

ExampleController.php

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;

class ExampleController extends Controller
{

    public function index()
    {
        $data = [
            'name' => 'Lang Ltd',
            'city' => 'Little Rock',
            'address' => '1231  Fittro Street',
            'postcode' => '72210',
            'image' => '', // UploadedFile or path to image
            'active' => true,
            'agents' => [ // relation
                [
                    'name' => 'Jose B. Pauli',
                    'phone' => '602-697-8030',
                    'email' => 'JoseBPauli@dayrep.com',
                    'position' => 'Manager',
                    'active' => true
                ],
                [
                    'name' => 'Sherry B. Crider',
                    'phone' => '301-967-7367',
                    'email' => 'SherryBCrider@jourrapide.com',
                    'position' => 'Bellhop',
                    'active' => true
                ]
            ]
        ];

        Provider::create($data);
    }
}
```