<?php

namespace Spatie\Permission\Traits;

use Spatie\Permission\Guard;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Group;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

trait HasGroups
{
    use HasRoles;

    private $groupClass;

    public static function bootHasGroups()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->groups()->detach();
        });
    }

    /**
     * @return Group
     */
    public function getGroupClass()
    {
        if (! isset($this->groupClass)) {
            $this->groupClass = app(PermissionRegistrar::class)->getGroupClass();
        }

        return $this->groupClass;
    }

    /**
     * Direct permission of group
     *
     * @param string|int|array|\Spatie\Permission\Contracts\Group|\Illuminate\Support\Collection $groups
     *
     * @return bool
     */
    public function hasGroup($groups): bool
    {
        if (is_string($groups) && false !== strpos($groups, '|')) {
            $groups = $this->convertPipeToArray($groups);
        }

        if (is_string($groups)) {
            return $this->groups->contains('name', $groups);
        }

        if (is_int($groups)) {
            return $this->groups->contains('id', $groups);
        }

        if ($groups instanceof Group) {
            return $this->groups->contains('id', $groups->id);
        }

        if (is_array($groups)) {
            foreach ($groups as $role) {
                if ($this->hasGroup($role)) {
                    return true;
                }
            }

            return false;
        }

        return $groups->intersect($this->groups)->isNotEmpty();
    }

    /**
     * Determine if the model has, via group, the given permission.
     *
     * @param \Spatie\Permission\Contracts\Permission $permission
     *
     * @return bool
     */
    protected function hasPermissionViaGroup(Permission $permission): bool
    {
        $groups = $this->groups;
        if ($groups) {
            /** @var Group $group */
            foreach ($groups as $group) {
//                dd($group->hasPermissionTo($permission));
                // case set permission direct to group
                if ($group->hasPermissionTo($permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A model may have multiple roles.
     */
    public function groups(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.group'),
            'model',
            config('permission.table_names.model_has_groups'),
            config('permission.column_names.model_morph_key'),
            'group_id'
        );
    }

    public function assignGroup(...$groups)
    {
        $groups = collect($groups)
            ->flatten()
            ->map(function ($group) {
                if (empty($group)) {
                    return false;
                }

                return $this->getStoredGroup($group);
            })
            ->filter(function ($group) {
                return $group instanceof Group;
            })
            ->each(function ($group) {
                $this->ensureModelSharesGuard($group);
            })
            ->map->id
            ->all();

        $model = $this->getModel();

        if ($model->exists) {
            $this->groups()->sync($groups, false);
            $model->load('groups');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($groups, $model) {
                    static $modelLastFiredOn;
                    if ($modelLastFiredOn !== null && $modelLastFiredOn === $model) {
                        return;
                    }
                    $object->groups()->sync($groups, false);
                    $object->load('groups');
                    $modelLastFiredOn = $object;
                });
        }

        $this->forgetCachedPermissions();

        return $this;
    }

    public function getStoredGroup($group): Group
    {
        $groupClass = $this->getGroupClass();

        if (is_numeric($group)) {
            return $groupClass->findById($group, $this->getDefaultGuardName());
        }

        if (is_string($group)) {
            return $groupClass->findByName($group, $this->getDefaultGuardName());
        }

        return $group;
    }

    /**
     * Revoke the given role from the model.
     *
     * @param string|\Spatie\Permission\Contracts\Group $group
     */
    public function removeGroup($group)
    {
        $this->groups()->detach($this->getStoredGroup($group));

        $this->load('groups');

        $this->forgetCachedPermissions();

        return $this;
    }

    /**
     * Remove all current roles and set the given ones.
     *
     * @param array|\Spatie\Permission\Contracts\Group|string ...$groups
     *
     * @return $this
     */
    public function syncGroup(...$groups)
    {
        $this->groups()->detach();

        return $this->assignGroup($groups);
    }
}
