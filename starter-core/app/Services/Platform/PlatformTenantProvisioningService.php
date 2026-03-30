<?php

namespace App\Services\Platform;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenant\TenantCompanyOperationalService;
use App\Services\Users\UserService;
use App\Support\Logging\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformTenantProvisioningService
{
    public function __construct(
        private readonly TenantCompanyOperationalService $tenantCompanyOperational,
        private readonly UserService $userService,
    ) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listTenantsForPlatform(Request $request): array
    {
        $this->tenantCompanyOperational->syncExpiredOperationalStatuses();

        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOLEAN);

        $q = Tenant::query()->orderBy('codigo');
        if ($includeInactive) {
            $q->visibleOperationally();
        } else {
            $q->activeOperationally();
        }

        $items = $q->get([
            'id',
            'codigo',
            'nombre',
            'slug',
            'activo',
            'operational_status',
            'inactivated_at',
            'inactivated_by',
            'reactivated_at',
            'reactivated_by',
            'subscription_status',
            'trial_starts_at',
            'trial_ends_at',
            'created_at',
            'correo_electronico',
            'codigo_postal',
            'tipo_vialidad',
            'calle',
            'numero_exterior',
            'numero_interior',
            'colonia',
            'localidad',
            'municipio',
            'estado',
        ]);

        $tenantIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $primaryAdmins = $this->primaryTenantAdminsByTenantIds($tenantIds);

        return [
            'items' => $items->map(function (Tenant $t) use ($primaryAdmins) {
                $admin = $primaryAdmins[$t->id] ?? null;

                return [
                    'id' => $t->id,
                    'codigo' => $t->codigo,
                    'nombre' => $t->nombre,
                    'slug' => $t->slug,
                    'activo' => (bool) $t->activo,
                    'operational_status' => $t->operational_status,
                    'inactivated_at' => $t->inactivated_at?->toISOString(),
                    'inactivated_by' => $t->inactivated_by,
                    'reactivated_at' => $t->reactivated_at?->toISOString(),
                    'reactivated_by' => $t->reactivated_by,
                    'subscription_status' => $t->subscription_status,
                    'trial_starts_at' => $t->trial_starts_at?->toISOString(),
                    'trial_ends_at' => $t->trial_ends_at?->toISOString(),
                    'created_at' => $t->created_at?->toISOString(),
                    'correo_electronico' => $t->correo_electronico,
                    'codigo_postal' => $t->codigo_postal,
                    'tipo_vialidad' => $t->tipo_vialidad,
                    'calle' => $t->calle,
                    'numero_exterior' => $t->numero_exterior,
                    'numero_interior' => $t->numero_interior,
                    'colonia' => $t->colonia,
                    'localidad' => $t->localidad,
                    'municipio' => $t->municipio,
                    'estado' => $t->estado,
                    'initial_admin' => $admin,
                ];
            })->values()->all(),
            'total' => $items->count(),
        ];
    }

    /**
     * Usuario “principal” con rol `admin` en el tenant: el de menor id entre los que tienen ese rol
     * (en la práctica suele coincidir con el admin inicial creado desde Plataforma).
     *
     * @param  array<int, int>  $tenantIds
     * @return array<int, array{id: int, usuario: string, codigo_cliente: string|null, activo: bool}>
     */
    private function primaryTenantAdminsByTenantIds(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }

        $rows = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('r.slug', 'admin')
            ->whereIn('r.tenant_id', $tenantIds)
            ->orderBy('r.tenant_id')
            ->orderBy('u.id')
            ->select([
                'r.tenant_id',
                'u.id as user_id',
                'u.usuario',
                'u.codigo_cliente',
                'u.activo',
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $tid = (int) $row->tenant_id;
            if (isset($out[$tid])) {
                continue;
            }
            $out[$tid] = [
                'id' => (int) $row->user_id,
                'usuario' => (string) $row->usuario,
                'codigo_cliente' => $row->codigo_cliente !== null ? (string) $row->codigo_cliente : null,
                'activo' => (bool) $row->activo,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTenant(array $data, int $actorUserId): Tenant
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $slug = $this->slugFromCodigo((string) $data['codigo']);
            $trialDays = (int) config('trial.default_trial_days', 14);

            $fiscalKeys = [
                'tipo_contribuyente',
                'origen_datos',
                'rfc',
                'nombre_fiscal',
                'regimen_fiscal_principal',
                'codigo_postal',
                'tipo_vialidad',
                'calle',
                'numero_exterior',
                'numero_interior',
                'colonia',
                'localidad',
                'municipio',
                'estado',
                'correo_electronico',
                'curp',
                'pf_nombre',
                'pf_primer_apellido',
                'pf_segundo_apellido',
            ];
            $fiscalData = array_intersect_key($data, array_flip($fiscalKeys));

            $tenant = Tenant::query()->create([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'slug' => $slug,
                'activo' => $data['activo'] ?? true,
                'operational_status' => Tenant::OPERATIONAL_ACTIVE,
                'trial_starts_at' => now(),
                'trial_ends_at' => now()->addDays(max(1, $trialDays)),
                'subscription_status' => Tenant::SUBSCRIPTION_TRIAL,
                ...$fiscalData,
            ]);

            Log::channel('security')->info('platform.tenant.created', [
                'actor_user_id' => $actorUserId,
                'tenant_id' => $tenant->id,
                'tenant_codigo' => $tenant->codigo,
                'tenant_slug' => $tenant->slug,
            ]);

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTenantInitialAdmin(string $tenantCodigo, array $data, int $actorUserId): User
    {
        return DB::transaction(function () use ($tenantCodigo, $data, $actorUserId) {
            /** @var Tenant $tenant */
            $tenant = Tenant::query()->where('codigo', $tenantCodigo)->firstOrFail();

            $this->userService->ensureCanCreateUserForTenant($tenant->id);

            $roles = $this->ensureBaseRolesForTenant($tenant, $actorUserId);
            $adminRole = $roles['admin'];

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'codigo_cliente' => $data['admin_codigo_cliente'] ?? null,
                'usuario' => $data['admin_usuario'],
                'password_hash' => $data['admin_password'],
                'activo' => true,
                'fecha_alta' => now()->toDateString(),
                // El admin inicial es admin de tenant, NO super admin de plataforma.
                'is_platform_admin' => false,
            ]);

            // Para asegurar comportamiento consistente con pertenencia N:N.
            $user->tenants()->syncWithoutDetaching([$tenant->id]);
            $user->roles()->syncWithoutDetaching([$adminRole->id]);

            AdminAuditLogger::userCreated($actorUserId, $user->id, $tenant->id);
            AdminAuditLogger::userRolesAttached($actorUserId, $user->id, $tenant->id, 1);

            return $user->fresh(['roles']);
        });
    }

    /**
     * @return array{super_admin: Role, admin: Role, user: Role}
     */
    private function ensureBaseRolesForTenant(Tenant $tenant, int $actorUserId): array
    {
        $templates = [
            ['slug' => 'super_admin', 'nombre' => 'Super administrador', 'descripcion' => 'Acceso total del tenant'],
            ['slug' => 'admin', 'nombre' => 'Administrador', 'descripcion' => 'Administración operativa'],
            ['slug' => 'user', 'nombre' => 'Usuario', 'descripcion' => 'Usuario estándar'],
        ];

        $out = [];

        foreach ($templates as $tpl) {
            $role = Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $tpl['slug'])
                ->first();

            if ($role === null) {
                $role = Role::query()->create([
                    'tenant_id' => $tenant->id,
                    'slug' => $tpl['slug'],
                    'nombre' => $tpl['nombre'],
                    'descripcion' => $tpl['descripcion'],
                ]);

                AdminAuditLogger::roleCreated($actorUserId, $role->id, $tenant->id);
            }

            $out[$tpl['slug']] = $role;
        }

        return $out;
    }

    /**
     * Transición manual trial → active|suspended (reglas existentes del controlador).
     */
    public function updateSubscriptionTrialTransition(string $tenantCodigo, string $newStatus, ?int $actorUserId): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->where('codigo', $tenantCodigo)->firstOrFail();

        if ($tenant->subscription_status !== Tenant::SUBSCRIPTION_TRIAL) {
            throw ValidationException::withMessages([
                'subscription_status' => ['Solo se puede cambiar desde estado trial.'],
            ]);
        }

        $tenant->subscription_status = $newStatus;
        $tenant->save();

        Log::channel('security')->info('platform.tenant.subscription_manual_update', [
            'actor_user_id' => $actorUserId,
            'tenant_id' => $tenant->id,
            'tenant_codigo' => $tenant->codigo,
            'subscription_status' => $tenant->subscription_status,
        ]);

        return $tenant->fresh();
    }

    private function slugFromCodigo(string $codigo): string
    {
        $slug = Str::slug($codigo);
        if ($slug === '') {
            $slug = strtolower(Str::random(10));
        }

        return $slug;
    }
}

