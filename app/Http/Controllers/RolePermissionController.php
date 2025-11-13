<?php
namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function getRoles()
    {
        $roles = Role::all();
        return response()->json(['role' => $roles], 200);
    }
    public function createRole(Request $request)
    {
        // return response()->json([$request->all()], 200);
        // exit;
        $data = $request->validate([
            'name' => 'required|string|unique:roles,name',
            // Add other validation rules if needed
        ]);
        $role = new Role();
        $role->name = $request->name;
        $role->guard_name = 'api';
        $role->save();
        return response()->json(['message' => 'Role created successfully', 'status' => true], 201);
    }
    public function EditRole(string $id)
    {
        $roles = Role::find($id);
        return response()->json(['role' => $roles], 200);
    }

    public function UpdateRole(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:roles,name',
            // Add other validation rules if needed
        ]);
        $role = Role::find($request->id);
        $role->name = $request->name;
        $role->guard_name = 'api';
        $role->save();
        return response()->json(['message' => 'Role Updated successfully',  'status' => true], 201);
    }

    // permission
    public function getPermissions()
    {
        $Permissions = Permission::all();
        return response()->json(['Permission' => $Permissions], 200);
    }

    public function createPermission(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = new Permission();
        $permission->name = $request->name;
        $permission->guard_name = 'api';
        $permission->save();

        return response()->json(['message' => 'Permission created successfully', 'Permission' => $permission], 201);
    }

    public function EditPermission(string $id)
    {
        $Permissions = Permission::find($id);
        return response()->json(['Permission' => $Permissions], 200);
    }

    public function UpdatePermission(Request $request)
    {
        $permission = Permission::findOrFail($request->id);
        if ($permission) {
            $permission->name = $request->name;
            $permission->guard_name = 'api';
            $permission->save();

            return response()->json(['message' => 'Permission updated successfully', 'permission' => $permission], 201);
        } else {
            return response()->json(['message' => 'Permission not found'], 404);
        }
    }

    public function getRolePermissions($id)
    {
        $role = Role::find($id);
        $permissions = $role->permissions->pluck('id');
        // $permissionsName = $role->permissions->pluck('name');
        // print_r($permissions);
        // exit;
        $permission = Permission::all();
        return response()->json(['id' => $id, 'AllPermission' => $permission, 'exist' => $permissions,'roleName' =>$role->name], 200);
    }
    public function giveRolePermissions(Request $request, $id)
    {
        $permission_ids = $request->permission_id;
        $role = Role::findById($request->id, 'api');
        if ($role) {
            $role->syncPermissions([]);
            $permissions = Permission::whereIn('id', $permission_ids)->get();
            foreach ($permissions as $permission) {
                $role->givePermissionTo($permission);
            }

            // The role now has the specified permissions
            return response()->json(['message' => 'Permissions assigned successfully']);
        } else {
            return response()->json(['message' => 'Role not found'], 404);
        }
    }

    public function getUserPermissions($id)
    {
        $user = User::find($id);
        $userPermissions = $user->permissions->pluck('id');

        // Retrieve permissions inherited from user roles
        $rolePermissions = $user->getAllPermissions()->pluck('id');

        // Merge both sets of permissions and remove duplicates
        //$permissions = $userPermissions->merge($rolePermissions)->unique();
      $permissions = $userPermissions->merge($rolePermissions)->unique()->values()->all();
        $permission = Permission::all();
        return response()->json(['id' => $id, 'AllPermission' => $permission, 'exist' => $permissions], 200);
    }
    public function giveUserPermissions(Request $request, $id)
    {
        $permission_ids = $request->permission_id;
        $user = User::findOrFail($id);
      
        if ($user) {
            // Assign the permissions to the user
            $user->syncPermissions([]);
            $permissions = Permission::whereIn('id', $permission_ids)->get();
            foreach ($permissions as $permission) {
                $user->givePermissionTo($permission);
            }

            // The role now has the specified permissions
            return response()->json(['message' => 'Permissions assigned successfully']);
        } else {
            return response()->json(['message' => 'User not found'], 404);
        }
        // return response()->json(['id'=>$id,'AllPermission'=>$request->all()],200);
    }

}
