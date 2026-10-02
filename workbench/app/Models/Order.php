<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $reference
 * @property string $status
 * @property int $amount
 * @property int $quantity
 */
class Order extends Model
{
    protected $guarded = [];
}
