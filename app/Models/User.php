<?php

namespace App\Models;

use App\Enums\Perfil;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Auditavel;
use App\Support\TenantAtual;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tenant_id', 'name', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use Auditavel, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Default também em memória, não só no banco: coluna booleana vem `null`
     * num model recém criado, e o Gate compara com `=== true`. Mesma regra
     * dos outros models, coberta por DefaultsEmMemoriaTest.
     */
    protected $attributes = [
        'dono_do_produto' => false,
    ];

    /**
     * Escopo próprio, e não o `DoTenant` comum, porque o `User` tem uma regra
     * a mais: ele é consultado pelo provider de autenticação, antes de existir
     * sessão.
     *
     * Com tenant resolvido pelo host, a busca fica restrita a ele. É o que
     * impede a credencial de um cliente de entrar no domínio próprio de outro,
     * e continua coberto por LoginEntreTenantsTest.
     *
     * Sem tenant no host, que é o caso do domínio do produto, a busca é global.
     * É assim que todo cliente entra pela mesma porta. Depois da autenticação
     * o `DefinirTenantDoUsuario` fixa o tenant a partir do próprio usuário, e
     * daí em diante todo dado volta a ser escopado normalmente.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $id = app(TenantAtual::class)->id();

            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.tenant_id', $id);
            }
        });

        static::creating(function (self $user): void {
            if ($user->getAttribute('tenant_id') === null) {
                $user->setAttribute('tenant_id', app(TenantAtual::class)->id());
            }
        });
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
            'ultimo_acesso_em' => 'datetime',
            // Fora do `#[Fillable]` de propósito: só o comando
            // `produto:definir-dono` escreve aqui. Ver a migration.
            'dono_do_produto' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Emitentes aos quais este usuário tem acesso.
     *
     * @return BelongsToMany<Emitente, $this>
     */
    public function emitentes(): BelongsToMany
    {
        return $this->belongsToMany(Emitente::class)->withTimestamps();
    }

    /**
     * Todo dado fiscal é isolado por emitente. Sem vínculo, sem acesso.
     */
    public function podeAcessar(Emitente $emitente): bool
    {
        return $this->emitentes()->whereKey($emitente->getKey())->exists();
    }

    /**
     * As roles do spatie são escopadas por emitente (teams), então a checagem
     * global precisa olhar o pivô direto, sem o escopo do time corrente.
     */
    public function eAdministradorEmAlgumEmitente(): bool
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $this->getMorphClass())
            ->where(config('permission.column_names.model_morph_key'), $this->getKey())
            ->whereIn('role_id', Role::query()->where('name', Perfil::Administrador->value)->pluck('id'))
            ->exists();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
