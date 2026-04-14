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
    public const ADMIN_VIEWER = 'viewer';
    public const ADMIN_SUPER_LEGACY = 'super_admin';
    public const CLIENT_HQ = 'hq_staff';
    public const CLIENT_KINDERGARTEN = 'kindergarten';
    public const CLIENT_SSU = 'ssu';

    protected $fillable = [
        'name','email','phone_number','address','role','sub_role','password','profile_photo_path','region_states'
    ];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'region_states' => 'array'];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isTechnician(): bool { return $this->role === self::ROLE_TECHNICIAN; }
    public function isClient(): bool { return $this->role === self::ROLE_CLIENT; }
    public function isViewer(): bool { return $this->role === self::ROLE_ADMIN && in_array($this->sub_role, [self::ADMIN_VIEWER, self::ADMIN_SUPER_LEGACY], true); }
    public function isSuperAdmin(): bool { return $this->isViewer(); }
    public function isMapsAdmin(): bool { return $this->role === self::ROLE_ADMIN && $this->sub_role === self::ADMIN_MAPS; }
    public function isAimAdmin(): bool { return $this->role === self::ROLE_ADMIN && $this->sub_role === self::ADMIN_AIM; }
    public function isSsu(): bool { return $this->role === self::ROLE_CLIENT && $this->sub_role === self::CLIENT_SSU; }
    public function canOpenMapsFinanceSupport(): bool { return $this->isAimAdmin(); }

    public function handledClientRoles(): array
    {
        return match ($this->sub_role) {
            self::ADMIN_MAPS => [self::CLIENT_KINDERGARTEN, self::CLIENT_SSU],
            self::ADMIN_AIM => [self::CLIENT_HQ],
            self::ADMIN_VIEWER, self::ADMIN_SUPER_LEGACY => [self::CLIENT_HQ, self::CLIENT_KINDERGARTEN, self::CLIENT_SSU],
            default => [],
        };
    }

    public function clientRequests()
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function primaryHandledClientRole(): ?string
    {
        return $this->handledClientRoles()[0] ?? null;
    }

    public function handlesClientRole(string $role): bool
    {
        return in_array($role, $this->handledClientRoles(), true);
    }

    public function profilePhotoUrl(): string
    {
        return $this->profile_photo_path
            ? route('files.show', ['encodedPath' => rtrim(strtr(base64_encode($this->profile_photo_path), '+/', '-_'), '=')])
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=0D6EFD&color=fff';
    }

    public static function stateOptions(): array
    {
        return [
            'Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Selangor','Terengganu','Sabah','Sarawak','Kuala Lumpur','Putrajaya','Labuan',
        ];
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN => match ($this->sub_role) {
                self::ADMIN_MAPS => 'Admin MAPS',
                self::ADMIN_AIM => 'Admin AIM',
                self::ADMIN_VIEWER, self::ADMIN_SUPER_LEGACY => 'Viewer',
                default => 'Admin',
            },
            self::ROLE_TECHNICIAN => 'Technician',
            self::ROLE_CLIENT => match ($this->sub_role) {
                self::CLIENT_HQ => 'HQ Staff',
                self::CLIENT_KINDERGARTEN => 'Kindergarten',
                self::CLIENT_SSU => 'SSU',
                default => 'Client',
            },
            default => ucfirst($this->role),
        };
    }
}
