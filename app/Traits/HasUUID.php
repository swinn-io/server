<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUUID
{
    /**
     * Boot model class.
     */
    protected static function bootHasUUID(): void
    {
        static::creating(function (Model $model) {
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Do not increment primary key.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Return primary key type.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}
