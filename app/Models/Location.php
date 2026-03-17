<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    public const TYPE_HQ = 'hq';
    public const TYPE_BRANCH = 'branch';

    protected $fillable = ['name', 'type', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_HQ ? 'HQ Location' : 'Branch';
    }
}
