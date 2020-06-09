<?php

namespace Spatie\Permission\Models;

use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\GroupDoesNotExist;
use Spatie\Permission\Guard;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasGroups;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Exceptions\GroupAlreadyExists;
use Spatie\Permission\Contracts\Group as GroupContract;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\RefreshesPermissionCache;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model implements GroupContract
{
    use HasGroups;
    use RefreshesPermissionCache;

    protected $guarded = ['id'];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->setTable(config('permission.table_names.groups'));
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        if (static::where('name', $attributes['name'])->where('guard_name', $attributes['guard_name'])->first()) {
            throw GroupAlreadyExists::create($attributes['name'], $attributes['guard_name']);
        }

        if (isNotLumen() && app()::VERSION < '5.4') {
            return parent::create($attributes);
        }

        return static::query()->create($attributes);
    }

    /**
     * A role may be given various permissions.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.permission'),
            config('permission.table_names.group_has_permissions'),
            'group_id',
            'permission_id'
        );
    }



    /**
     * A group belongs to some users of the model associated with its guard.
     */
    public function users(): MorphToMany
    {
        return $this->morphedByMany(
            getModelForGuard($this->attributes['guard_name']),
            'model',
            config('permission.table_names.model_has_groups'),
            'role_id',
            config('permission.column_names.model_morph_key')
        );
    }

    /**
     * A group belongs to some users of the model associated with its guard.
     */
    public function roles(): belongsToMany
    {
        return $this->belongsToMany(
            config('permission.models.role'),
            config('permission.table_names.group_has_roles'),
            'group_id',
            'role_id'
        );
    }

    /**
     * Find a role by its name and guard name.
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Role|\Spatie\Permission\Models\Role
     *
     * @throws \Spatie\Permission\Exceptions\RoleDoesNotExist
     */
    public static function findByName(string $name, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::getGroups(['name' => $name, 'guard_name' => $guardName])->first();

        if (! $role) {
            throw GroupDoesNotExist::named($name);
        }

        return $role;
    }

    public static function findById(int $id, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::getGroups(['id' => $id, 'guard_name' => $guardName])->first();

        if (! $role) {
            throw GroupDoesNotExist::withId($id);
        }

        return $role;
    }

    /**
     * Find or create role by its name (and optionally guardName).
     *
     * @param string $name
     * @param string|null $guardName
     *
     * @return \Spatie\Permission\Contracts\Role
     */
    public static function findOrCreate(string $name, $guardName = null): GroupContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::getGroups(['name' => $name, 'guard_name' => $guardName])->first();

        if (! $role) {
            return static::query()->create(['name' => $name, 'guard_name' => $guardName]);
        }

        return $role;
    }

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|Permission $permission
     *
     * @return bool
     *
     * @throws \Spatie\Permission\Exceptions\GuardDoesNotMatch
     */
    public function hasPermissionTo($permission): bool
    {
        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByName($permission, $this->getDefaultGuardName());
        }

        if (is_int($permission)) {
            $permission = $permissionClass->findById($permission, $this->getDefaultGuardName());
        }

        if (! $this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        /** @var Role $role */
        foreach ($this->roles as $role) {
            if ($role->hasPermissionTo($permission)) {
                return true;
            }
        };

        return $this->permissions->contains('id', $permission->id);
    }
    /**
     * Get the current cached roles.
     */
    public static function getGroups($params) : Collection {

        return app(PermissionRegistrar::class)
            ->setGroupClass(static::class)
            ->getGroups($params);
    }
}
