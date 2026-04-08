<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    use HasFactory;

    public const TARGET_HQ = 'hq_staff';
    public const TARGET_KINDERGARTEN = 'kindergarten';
    public const TARGET_BOTH = 'both';

    protected $fillable = [
        'name',
        'role_scope',
        'urgency_enabled',
        'attachment_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'urgency_enabled' => 'boolean',
            'attachment_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function questions()
    {
        return $this->hasMany(RequestQuestion::class)->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function scopeVisibleToClientRole($query, string $clientSubRole)
    {
        if ($clientSubRole === User::CLIENT_HQ) {
            return $query->whereIn('role_scope', [self::TARGET_HQ, self::TARGET_BOTH]);
        }

        return $query->whereIn('role_scope', [self::TARGET_KINDERGARTEN, self::TARGET_BOTH]);
    }

    public function roleScopeLabel(): string
    {
        return match ($this->role_scope) {
            self::TARGET_HQ => 'HQ Staff',
            self::TARGET_KINDERGARTEN => 'Kindergarten',
            default => 'Both',
        };
    }
}
