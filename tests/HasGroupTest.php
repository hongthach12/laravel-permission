<?php

namespace Spatie\Permission\Test;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Contracts\Role;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Group;
use Spatie\Permission\Models\Permission;

class HasGroupTest extends TestCase
{
    /** @test */
    public function it_can_determine_that_the_user_does_not_have_a_group()
    {
        $this->assertFalse($this->testUser->hasGroup('testGroup'));
    }

    /** @test */
    public function it_can_assign_and_remove_a_group()
    {
        $this->assertFalse($this->testUser->hasGroup('testGroup'));

        $this->testUser->assignGroup('testGroup');

        $this->assertTrue($this->testUser->hasGroup('testGroup'));

        $this->testUser->removeGroup('testGroup');

        $this->assertFalse($this->testUser->hasGroup('testGroup'));

    }
    /** @test */

    public function it_can_assign_role_to_a_group()
    {
        $this->testUserRole->assignGroup($this->testGroup);
        $this->testUserPermission->assignRole($this->testUserRole);

        $this->assertTrue($this->testUserRole->hasGroup('testGroup'));

        $this->testGroup->givePermissionTo(Permission::create(['name' => 'permission_direct_to_group']));

        $this->testUser->assignGroup('testGroup');
        $this->testGroup->hasPermissionTo('permission_direct_to_group');

        $this->assertTrue($this->testUserRole->hasPermissionTo($this->testUserPermission));

        $this->assertTrue($this->testUser->hasRole('testRole'));

        $this->assertTrue($this->testUser->hasPermissionTo($this->testUserPermission));
        $this->assertTrue($this->testUser->hasPermissionTo('permission_direct_to_group'));

        $this->testUser->givePermissionTo(Permission::create(['name' => 'permission_direct_to_user']));

        $this->assertTrue($this->testUser->hasPermissionTo('permission_direct_to_user'));

        // remove group and recheck

        $this->testUser->removeGroup($this->testGroup);

        $this->assertFalse($this->testUser->hasGroup('testGroup'));
        $this->assertFalse($this->testUser->hasRole('testRole'));
        $this->assertFalse($this->testUser->hasPermissionTo('permission_direct_to_group'));
        $this->assertFalse($this->testUser->hasPermissionTo($this->testUserPermission));

        // new user

        $newUser = User::create(['email' => 'test2@user.com']);

        $this->assertFalse($newUser->hasRole('testRole'));
        $this->assertFalse($newUser->hasPermissionTo($this->testUserPermission));
        $this->assertFalse($newUser->hasPermissionTo('permission_direct_to_group'));

    }
}
