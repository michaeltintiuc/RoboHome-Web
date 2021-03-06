<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    protected $fillable = ['name', 'description', 'type'];
    protected $table = 'devices';

    public function add(string $name, string $description, string $userId, int $type) : Device
    {
        $this->name = $name;
        $this->description = $description;
        $this->user_id = $userId;
        $this->device_type_id = $type;
        $this->save();

        return $this;
    }

    public function rfDevice() : HasOne
    {
        return $this->hasOne(RFDevice::class);
    }

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($device) {
            $device->rfDevice()->delete();
        });
    }
}
