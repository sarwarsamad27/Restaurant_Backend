<?php

namespace App\Models;

use App\Models\Concerns\StoresNativeTypes;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Base class for every collection in the app's MongoDB database.
 */
abstract class MongoModel extends Model
{
    use StoresNativeTypes;

    protected $connection = 'mongodb';
}
