<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClubName;
use App\Enums\RoleName;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Dtos\ExternalLinkPayloadDto;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\DatabaseNotificationCollection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Chat> $chats
 * @property-read int|null $chats_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Roles ya leídos de la base. Cada petición pregunta por el rol y por si
     * es administrador; sin esto eran dos consultas por render.
     *
     * @var array<int, string>|null
     */
    private ?array $cachedRoleNames = null;

    public static function fromExternalLink(
        ExternalLinkPayloadDto $payload
    ): ?self {
        $user = self::updateOrCreate(
            [
                'club_name' => $payload->getClub(),
                'external_id' => $payload->getExternalId(),
            ],
            [
                'name' => $payload->getUserName(),
                'email' => $payload->getClub().'-'.$payload->getExternalId().'@external.local',
                'password' => Str::random(40),
            ]
        );

        $role = Role::where('name', $payload->getRole())->first();

        if ($role !== null) {
            $user->roles()->sync([$role->id]);
            $user->cachedRoleNames = null;
        }

        return $user;
    }

    /**
     * Get the user that the OAuth connection belongs to.
     *
     * @return HasMany<Chat, covariant $this>
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Club al que pertenece el usuario, cuando la columna trae un valor
     * conocido. Los enlaces externos pueden traer clubes que aún no existen
     * en el enum.
     */
    public function clubName(): ?ClubName
    {
        $club = $this->getAttributes()['club_name'] ?? null;

        return is_string($club) ? ClubName::tryFrom($club) : null;
    }

    /**
     * Primer rol del usuario. La aplicación asigna uno solo por usuario, pero
     * la relación es de muchos a muchos.
     */
    public function primaryRole(): ?RoleName
    {
        $role = $this->roleNames()[0] ?? null;

        return is_string($role) ? RoleName::tryFrom($role) : null;
    }

    public function isAdmin(): bool
    {
        return in_array('admin', $this->roleNames(), true);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    protected static function boot(): void
    {
        self::creating(function (self $model): void {
            if ($model->club_name !== null && $model->club_token !== null) {
                $model->email = "{$model->club_token}@{$model->club_name}.local";
                $model->password = Str::password();
            }

            $model->email_verified_at = now();
            $model->remember_token = Str::random(10);
        });

        parent::boot();
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

    /**
     * @return array<int, string>
     */
    private function roleNames(): array
    {
        return $this->cachedRoleNames ??= $this->roles()->pluck('name')->all();
    }
}
