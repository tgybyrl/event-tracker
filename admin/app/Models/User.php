<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Every role the panel knows about.
     *
     * The column is a VARCHAR(20) with no constraint behind it, so this list is
     * what actually limits the values: the FormRequests validate against it and
     * both user forms build their <select> from it. Add a role here and the
     * rule and the dropdown both follow.
     */
    public const ROLES = ['manager', 'worker'];

    /**
     * Whether this user may reach the manager-only screens.
     *
     * Written once here so the string 'manager' does not get repeated in the
     * Gate, the sidebar and (later) the user-management controller.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

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
}
