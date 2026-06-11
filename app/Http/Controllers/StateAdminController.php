<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

class StateAdminController extends Controller
{
    // POST /api/state-admin/login
    public function login(Request $request)
    {
        $username = strtolower(trim($request->input('username', '')));
        $password = $request->input('password', '');

        if (!$username || !$password) {
            return response()->json(['success' => false, 'message' => 'Username and password required.'], 400);
        }

        $user = DB::table('stateadmins')->where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $token = $this->generateToken($user->id, $user->username, 'stateadmin');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'fullname' => $user->fullname,
                'username' => $user->username,
                'email'    => $user->email,
                'mobile'   => $user->mobile,
                'state'    => $user->state,
                'role'     => 'stateadmin',
            ],
        ]);
    }

    // POST /api/state-admin/add [JWT: superadmin]
    public function add(Request $request)
    {
        $fullname = trim($request->input('fullname', ''));
        $username = strtolower(trim($request->input('username', '')));
        $email    = strtolower(trim($request->input('email', '')));
        $mobile   = trim($request->input('mobile', ''));
        $password = $request->input('password', '');
        $state    = trim($request->input('state', ''));

        if (!$fullname || !$username || !$mobile || !$password || !$state) {
            return response()->json(['success' => false, 'message' => 'All fields required.'], 400);
        }

        if (!preg_match('/^\d{10}$/', $mobile)) {
            return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 400);
        }

        $dup = DB::table('stateadmins')
            ->where('username', $username)
            ->orWhere('mobile', $mobile)
            ->first();

        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Username or mobile already exists.'], 409);
        }

        // auth_user from JWT middleware
        $authUser = $request->input('auth_user');
        $createdby = is_object($authUser) ? $authUser->id : (is_array($authUser) ? $authUser['id'] : null);

        $id = DB::table('stateadmins')->insertGetId([
            'fullname'   => $fullname,
            'username'   => $username,
            'email'      => $email,
            'mobile'     => $mobile,
            'password'   => Hash::make($password),
            'state'      => $state,
            'createdby'  => $createdby,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'State admin added.', 'id' => $id], 201);
    }

    // GET /api/state-admin/get [JWT: superadmin]
    public function get(Request $request)
    {
        $admins = DB::table('stateadmins')
            ->select('id', 'fullname', 'username', 'email', 'mobile', 'state', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'count' => $admins->count(), 'data' => $admins]);
    }

    // PUT /api/state-admin/edit/{id} [JWT: superadmin]
    public function edit(Request $request, $id)
    {
        $user = DB::table('stateadmins')->where('id', $id)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'State admin not found.'], 404);
        }

        $update = ['updated_at' => now()];
        if ($request->input('fullname')) $update['fullname'] = trim($request->input('fullname'));
        if ($request->input('email'))    $update['email']    = strtolower(trim($request->input('email')));
        if ($request->input('mobile'))   $update['mobile']   = trim($request->input('mobile'));
        if ($request->input('state'))    $update['state']    = trim($request->input('state'));
        if ($request->input('password')) $update['password'] = Hash::make($request->input('password'));

        DB::table('stateadmins')->where('id', $id)->update($update);

        return response()->json(['success' => true, 'message' => 'State admin updated.']);
    }

    // DELETE /api/state-admin/delete/{id} [JWT: superadmin]
    public function delete(Request $request, $id)
    {
        $affected = DB::table('stateadmins')->where('id', $id)->delete();

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'State admin not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'State admin deleted.']);
    }

    private function generateToken($id, $username, $role)
    {
        $payload = [
            'iss'      => config('app.url'),
            'iat'      => time(),
            'exp'      => time() + (60 * 60 * 24 * 30),
            'id'       => $id,
            'username' => $username,
            'role'     => $role,
        ];

        return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    }
}