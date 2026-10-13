<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Factory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'username',
        'mobile',
        'password',
        'address',
        'employee_id',
        'designation',
        'status',
        'photo_path',
        'created_by',
    ];

    /**
     * Always hidden from serialization. NID columns are never exposed via the API.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'nid_encrypted',
        'nid_index',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Sensitive NID: encrypted at rest in `nid_encrypted`, with a keyed-hash blind
     * index in `nid_index` for exact-match lookup. Read/written via the virtual
     * `nid` attribute; the raw value is never persisted or serialized.
     */
    protected function nid(): Attribute
    {
        return Attribute::make(
            get: fn () => ! empty($this->attributes['nid_encrypted'])
                ? Crypt::decryptString($this->attributes['nid_encrypted'])
                : null,
            set: fn (?string $value) => [
                'nid_encrypted' => $value === null ? null : Crypt::encryptString($value),
                'nid_index' => $value === null ? null : self::nidIndex($value),
            ],
        );
    }

    public static function nidIndex(string $nid): string
    {
        return hash_hmac('sha256', $nid, config('app.key'));
    }

    public function scopeWhereNid(Builder $query, string $nid): Builder
    {
        return $query->where('nid_index', self::nidIndex($nid));
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company_access')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function factories(): BelongsToMany
    {
        return $this->belongsToMany(Factory::class, 'user_factory_access')
            ->withTimestamps();
    }

    public function canAccessCompany(int $companyId): bool
    {
        return $this->companies()->whereKey($companyId)->exists();
    }
}
