<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

class DistrictAdminController extends Controller
{
    // ============================================================
    // POST /api/district-admin/login
    // ============================================================
    public function login(Request $request)
    {
        $username = strtolower(trim($request->input('username', '')));
        $password = $request->input('password', '');

        if (!$username || !$password) {
            return response()->json(['success' => false, 'message' => 'Username and password required.'], 400);
        }

        $user = DB::table('districtadmins')->where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $token = $this->generateToken($user->id, $user->username, 'districtadmin');

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
                'district' => $user->district,
                'role'     => 'districtadmin',
            ],
        ]);
    }

    // ============================================================
    // POST /api/district-admin/add (Super Admin only)
    // ============================================================
    public function add(Request $request)
    {
        $fullname = trim($request->input('fullname', ''));
        $username = strtolower(trim($request->input('username', '')));
        $email    = strtolower(trim($request->input('email', '')));
        $mobile   = trim($request->input('mobile', ''));
        $password = $request->input('password', '');
        $state    = trim($request->input('state', ''));
        $district = trim($request->input('district', ''));

        if (!$fullname || !$username || !$mobile || !$password || !$state || !$district) {
            return response()->json(['success' => false, 'message' => 'All fields required.'], 400);
        }

        if (!preg_match('/^\d{10}$/', $mobile)) {
            return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 400);
        }

        $dup = DB::table('districtadmins')
            ->where('username', $username)
            ->orWhere('mobile', $mobile)
            ->first();

        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Username or mobile already exists.'], 409);
        }

        $authUser = $request->input('auth_user');

        $id = DB::table('districtadmins')->insertGetId([
            'fullname'   => $fullname,
            'username'   => $username,
            'email'      => $email,
            'mobile'     => $mobile,
            'password'   => Hash::make($password),
            'state'      => $state,
            'district'   => $district,
            'createdby'  => $authUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'District admin added.', 'id' => $id], 201);
    }

    // ============================================================
    // GET /api/district-admin/get (Super Admin only)
    // ============================================================
    public function get(Request $request)
    {
        $admins = DB::table('districtadmins')
            ->select('id', 'fullname', 'username', 'email', 'mobile', 'state', 'district', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'count' => $admins->count(), 'data' => $admins]);
    }

    // ============================================================
    // PUT /api/district-admin/edit/{id} (Super Admin only)
    // ============================================================
    public function edit(Request $request, $id)
    {
        $fullname = trim($request->input('fullname', ''));
        $email    = strtolower(trim($request->input('email', '')));
        $mobile   = trim($request->input('mobile', ''));
        $state    = trim($request->input('state', ''));
        $district = trim($request->input('district', ''));

        $affected = DB::table('districtadmins')
            ->where('id', $id)
            ->update([
                'fullname'   => $fullname,
                'email'      => $email,
                'mobile'     => $mobile,
                'state'      => $state,
                'district'   => $district,
                'updated_at' => now(),
            ]);

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'District admin not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'District admin updated.']);
    }

    // ============================================================
    // DELETE /api/district-admin/delete/{id} (Super Admin only)
    // ============================================================
    public function delete(Request $request, $id)
    {
        $affected = DB::table('districtadmins')->where('id', $id)->delete();

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'District admin not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'District admin deleted.']);
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
