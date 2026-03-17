<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_CLIENT = 'client';
    public const ADMIN_MAPS = 'maps_admin';
    public const ADMIN_AIM = 'aim_admin';
    public const CLIENT_HQ = 'hq_staff';
    public const CLIENT_TEACHER = 'teacher';
    public const CLIENT_PRINCIPAL = 'principal';

    protected $fillable = ['name','email','phone_number','address','role','sub_role','password','profile_photo_path'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isTechnician(): bool { return $this->role === self::ROLE_TECHNICIAN; }
    public function isClient(): bool { return $this->role === self::ROLE_CLIENT; }

    public function handledClientRoles(): array
    {
        return match ($this->sub_role) {
            self::ADMIN_MAPS => [self::CLIENT_TEACHER, self::CLIENT_PRINCIPAL],
            self::ADMIN_AIM => [self::CLIENT_HQ],
            default => [],
        };
    }



    public function clientRequests()
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function profilePhotoUrl(): string
    {
        return $this->profile_photo_path
            ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($this->profile_photo_path), '+/', '-_'), '=')])
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=0D6EFD&color=fff';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => $this->sub_role === self::ADMIN_MAPS ? 'Admin MAPS' : 'Admin AIM',
            self::ROLE_TECHNICIAN => 'Technician',
            self::ROLE_CLIENT => match ($this->sub_role) {
                self::CLIENT_HQ => 'HQ Staff',
                self::CLIENT_TEACHER => 'Teacher',
                self::CLIENT_PRINCIPAL => 'Principal',
                default => 'Client',
            },
            default => ucfirst($this->role),
        };
    }
}
