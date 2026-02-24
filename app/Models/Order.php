<?php

namespace App\Models;

use App\Models\Casts\UuidCast;
use Illuminate\Database\Eloquent\Model;
use Keepsuit\LaravelTemporal\Contracts\TemporalSerializable;
use Keepsuit\LaravelTemporal\Integrations\Eloquent\TemporalEloquentSerialize;
use Ramsey\Uuid\UuidInterface;

/**
 * @property UuidInterface $uuid
 * @property string $workflow_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $delivery_address
 */
class Order extends Model implements TemporalSerializable
{
    use TemporalEloquentSerialize;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    /** @link config/temporal.php section is integrations **/
    protected $fillable = [
        'id',
        'workflow_id',
        'customer_name',
        'customer_phone',
        'delivery_address',
    ];

    protected function casts(): array
    {
        return [
            'id' => UuidCast::class,
        ];
    }
}
