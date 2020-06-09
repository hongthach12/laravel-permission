<?php

namespace Spatie\Permission;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Group;
use Spatie\Permission\Contracts\Role;
use Illuminate\Contracts\Auth\Access\Gate;
use Spatie\Permission\Contracts\Permission;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class PermissionRegistrar
{
    /** @var \Illuminate\Contracts\Auth\Access\Gate */
    protected $gate;

    /** @var \Illuminate\Contracts\Cache\Repository */
    protected $cache;

    /** @var \Illuminate\Cache\CacheManager */
    protected $cacheManager;

    /** @var string */
    protected $permissionClass;

    /** @var string */
    protected $roleClass;
    /** @var string */
    protected $groupClass;

    /** @var \Illuminate\Support\Collection */
    protected $permissions;

    /** @var \Illuminate\Support\Collection */
    protected $groups;

    /** @var DateInterval|int */
    public static $cacheExpirationTime;

    /** @var string */
    public static $cacheKey;

    public static $cacheGroupKey;

    /** @var string */
    public static $cacheModelKey;


    /**
     * PermissionRegistrar constructor.
     *
     * @param \Illuminate\Contracts\Auth\Access\Gate $gate
     * @param \Illuminate\Cache\CacheManager $cacheManager
     */
    public function __construct(Gate $gate, CacheManager $cacheManager)
    {
        $this->gate = $gate;
        $this->permissionClass = config('permission.models.permission');
        $this->roleClass = config('permission.models.role');
        $this->groupClass = config('permission.models.group');

        $this->cacheManager = $cacheManager;
        $this->initializeCache();
    }

    protected function initializeCache()
    {
        self::$cacheExpirationTime = config('permission.cache.expiration_time', config('permission.cache_expiration_time'));

        if (app()->version() <= '5.5') {
            if (self::$cacheExpirationTime instanceof \DateInterval) {
                $interval = self::$cacheExpirationTime;
                self::$cacheExpirationTime = $interval->m * 30 * 60 * 24 + $interval->d * 60 * 24 + $interval->h * 60 + $interval->i;
            }
        }

        self::$cacheKey = config('permission.cache.key');
        self::$cacheModelKey = config('permission.cache.model_key');
        self::$cacheGroupKey = config('permission.cache.key_group');



        $this->cache = $this->getCacheStoreFromConfig();
    }

    protected function getCacheStoreFromConfig(): \Illuminate\Contracts\Cache\Repository
    {
        // the 'default' fallback here is from the permission.php config file, where 'default' means to use config(cache.default)
        $cacheDriver = config('permission.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (! \array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Register the permission check method on the gate.
     *
     * @return bool
     */
    public function registerPermissions(): bool
    {
        $this->gate->before(function (Authorizable $user, string $ability) {
            try {
                if (method_exists($user, 'hasPermissionTo')) {
                    return $user->hasPermissionTo($ability) ?: null;
                }
            } catch (PermissionDoesNotExist $e) {
            }
        });

        return true;
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions()
    {
        $this->permissions = null;
        $this->groups = null;

        return $this->cache->forget(self::$cacheKey) && $this->cache->forget(self::$cacheGroupKey);
    }

    /**
     * Get the permissions based on the passed params.
     *
     * @param array $params
     *
     * @return \Illuminate\Support\Collection
     */
    public function getPermissions(array $params = []): Collection
    {
        if ($this->permissions === null) {
            $this->permissions = $this->cache->remember(self::$cacheKey, self::$cacheExpirationTime, function () {
                return $this->getPermissionClass()
                    ->with('roles')
                    ->get();
            });
        }

        $permissions = clone $this->permissions;

        foreach ($params as $attr => $value) {
            $permissions = $permissions->where($attr, $value);
        }

        return $permissions;
    }

    public function getGroups(array $params = []): Collection
    {
        if ($this->groups === null) {
            $this->groups = $this->cache->remember(self::$cacheGroupKey, self::$cacheExpirationTime, function () {
                return $this->getGroupClass()
                                ->with('roles')
                                 ->get();
            });
        }

        $groups = clone $this->groups;

        foreach ($params as $attr => $value) {
            $groups = $groups->where($attr, $value);
        }

        return $groups;
    }

    /**
     * Get an instance of the permission class.
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    public function getPermissionClass(): Permission
    {
        return app($this->permissionClass);
    }

    public function setPermissionClass($permissionClass)
    {
        $this->permissionClass = $permissionClass;

        return $this;
    }

    public function setGroupClass($groupClass)
    {
        $this->groupClass = $groupClass;

        return $this;
    }

    /**
     * Get an instance of the role class.
     *
     * @return \Spatie\Permission\Contracts\Role
     */
    public function getRoleClass(): Role
    {
        return app($this->roleClass);
    }

    /**
     * Get the instance of the Cache Store.
     *
     * @return \Illuminate\Contracts\Cache\Store
     */
    public function getCacheStore(): \Illuminate\Contracts\Cache\Store
    {
        return $this->cache->getStore();
    }

    /**
     * Get an instance of the role class.
     *
     * @return \Spatie\Permission\Contracts\Group
     */
    public function getGroupClass(): Group
    {
        return app($this->groupClass);
    }
}
