<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int, string> $scopes
 * @property array<int, string> $devices
 */
class ServiceClient extends Model
{
    protected $fillable = ['name', 'token_hash', 'scopes', 'devices', 'revoked_at'];

    protected $hidden = ['token_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scopes' => 'array', 'devices' => 'array', 'revoked_at' => 'datetime'];
    }
}
