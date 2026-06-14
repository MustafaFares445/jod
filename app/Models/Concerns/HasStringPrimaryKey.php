<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasStringPrimaryKey
{
    protected static function bootHasStringPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            $keyName = $model->getKeyName();

            if (empty($model->getAttribute($keyName))) {
                $model->setAttribute($keyName, (string) Str::uuid());
            }
        });
    }
}
