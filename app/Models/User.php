<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    // Guard used by Spatie Permission for roles and permissions
    protected string $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'phone', 'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // =========================================================================
    // Role helper methods — wraps Spatie's hasRole() for cleaner policy code.
    // Using these instead of hasRole('admin') directly means if you rename
    // a role later, you change one method here, not 10+ policy files.
    // =========================================================================

    // public function isAdmin(): bool
    // {
    //     return $this->hasRole('admin');
    // }

    // public function isLibrarian(): bool
    // {
    //     return $this->hasRole('librarian');
    // }

    // public function isMember(): bool
    // {
    //     return $this->hasRole('member');
    // }

    // public function isAdminOrLibrarian(): bool
    // {
    //     return $this->hasAnyRole(['admin', 'librarian']);
    // }
}
