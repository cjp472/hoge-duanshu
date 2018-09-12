<?php

return [

    'role' => 'App\Models\Rbac\Role',

    'roles_table' => 'roles',

    'role_foreign_key' => 'role_id',

    'user' => 'App\Models\User',

    'users_table' => 'users',

    'role_user_table' => 'role_user',

    'user_foreign_key' => 'user_id',

    'permission' => 'App\Models\Rbac\Permission',

    'permissions_table' => 'permissions',

    'permission_role_table' => 'permission_role',

    'permission_foreign_key' => 'permission_id',
];
