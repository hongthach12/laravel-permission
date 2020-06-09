<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class GroupDoesNotExist extends InvalidArgumentException
{
    public static function named(string $roleName)
    {
        return new static("There is no role named `{$roleName}`.");
    }

    public static function withId(int $roleId)
    {
        return new static("There is no role with id `{$roleId}`.");
    }
}
