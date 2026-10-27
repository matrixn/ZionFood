<?php

namespace App\Domain\Tenancy;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class StaffPermissions
{
    public const FEATURES = ['orders', 'reservations', 'tables', 'kitchen', 'menu'];

    public const LEVELS = ['none', 'view', 'manage'];

    public static function defaults(string $role): array
    {
        $permissions = array_fill_keys(self::FEATURES, 'manage');
        if ($role === 'staff') {
            $permissions['menu'] = 'view';
        }

        return $permissions;
    }

    public static function effective(OrganizationMembership $membership): array
    {
        $defaults = self::defaults($membership->role);
        if ($membership->role === 'owner') {
            return $defaults;
        }
        if ($membership->permissions === null) {
            return $defaults;
        }

        // An explicit matrix is fail-closed for malformed or missing entries.
        return collect(self::FEATURES)->mapWithKeys(fn ($key) => [$key => in_array($membership->permissions[$key] ?? null, self::LEVELS, true) ? $membership->permissions[$key] : 'none'])->all();
    }

    public static function validate(array $permissions): array
    {
        $rules = ['permissions' => ['required', 'array:'.implode(',', self::FEATURES)]];
        foreach (self::FEATURES as $feature) {
            $rules['permissions.'.$feature] = ['required', 'in:'.implode(',', self::LEVELS)];
        }
        Validator::make(['permissions' => $permissions], $rules)->validate();

        return array_intersect_key($permissions, array_flip(self::FEATURES));
    }

    public static function grant(User $actor, int $organization, string $role, ?array $requested): array
    {
        $permissions = $requested === null ? self::defaults($role) : self::validate($requested);
        $access = app(TenantAccess::class);
        foreach ($permissions as $feature => $level) {
            if ($level !== 'none') {
                abort_unless($access->canFeatureInOrganization($actor, $organization, $feature, $level === 'manage'), 403);
            }
        }

        return $permissions;
    }
}
