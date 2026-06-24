<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

class InspectionOfficerController extends Controller
{
    // ============================================================
    // POST /api/inspection-officer/login
    // ============================================================
    public function login(Request $request)
    {
        $username = strtolower(trim($request->input('username', '')));
        $password = $request->input('password', '');

        if (!$username || !$password) {
            return response()->json(['success' => false, 'message' => 'Username and password required.'], 400);
        }

        $user = DB::table('inspectionofficers')->where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        // $token = $this->generateToken($user->id, $user->username, 'inspectionofficer');

        $token = $this->generateToken($user->id, $user->username, 'inspectionofficer', $user->district, $user->state);


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
                'taluka'   => $user->taluka,
                'ward'     => $user->ward,
                'role'     => 'inspectionofficer',
            ],
        ]);
    }

    // ============================================================
    // POST /api/inspection-officer/add (District Admin only)
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
        $taluka   = trim($request->input('taluka', ''));
        $ward     = trim($request->input('ward', ''));

        if (!$fullname || !$username || !$mobile || !$password || !$state || !$district) {
            return response()->json(['success' => false, 'message' => 'All fields required.'], 400);
        }

        if (!preg_match('/^\d{10}$/', $mobile)) {
            return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 400);
        }

        $dup = DB::table('inspectionofficers')
            ->where('username', $username)
            ->orWhere('mobile', $mobile)
            ->first();

        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Username or mobile already exists.'], 409);
        }

        $authUser = $request->input('auth_user');

        $id = DB::table('inspectionofficers')->insertGetId([
            'fullname'   => $fullname,
            'username'   => $username,
            'email'      => $email,
            'mobile'     => $mobile,
            'password'   => Hash::make($password),
            'state'      => $state,
            'district'   => $district,
            'taluka'     => $taluka,
            'ward'       => $ward,
            // 'createdby'  => $authUser->id,
            // $authUser['id'] ?? null,
            'createdby'  => $authUser['id'] ?? null,  // ← FIXED
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Inspection officer added.', 'id' => $id], 201);
    }

    // ============================================================
    // GET /api/inspection-officer/get (District Admin only)
    // ============================================================
    // public function get(Request $request)
    // {
    //     $authUser = $request->input('auth_user');

    //     $officers = DB::table('inspectionofficers')
    //         ->select('id', 'fullname', 'username', 'email', 'mobile', 'state', 'district', 'taluka', 'ward', 'created_at')
    //         // ->where('createdby', $authUser->id)
    //                 ->where('createdby', $authUser['id'])  // ← array syntax

    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     return response()->json(['success' => true, 'count' => $officers->count(), 'data' => $officers]);
    // }


    public function get(Request $request)
{
    $authUser = $request->input('auth_user');
    $role     = $authUser['role']     ?? '';
    $district = $authUser['district'] ?? '';
    $id       = $authUser['id']       ?? null;

    $query = DB::table('inspectionofficers')
        ->select('id', 'fullname', 'username', 'email', 'mobile',
                 'state', 'district', 'taluka', 'ward', 'created_at')
        ->orderBy('created_at', 'desc');

    if ($role === 'inspectionofficer') {
        $query->where('id', $id);
    } elseif ($role === 'districtadmin' && $district) {
        $query->where('district', $district);
    }
    // superadmin → sagale

    $officers = $query->get();

    return response()->json([
        'success' => true,
        'count'   => $officers->count(),
        'data'    => $officers,
    ]);
}

    // ============================================================
    // PUT /api/inspection-officer/edit/{id} (District Admin only)
    // ============================================================
    public function edit(Request $request, $id)
    {
        $fullname = trim($request->input('fullname', ''));
        $email    = strtolower(trim($request->input('email', '')));
        $mobile   = trim($request->input('mobile', ''));
        $state    = trim($request->input('state', ''));
        $district = trim($request->input('district', ''));
        $taluka   = trim($request->input('taluka', ''));
        $ward     = trim($request->input('ward', ''));

        $affected = DB::table('inspectionofficers')
            ->where('id', $id)
            ->update([
                'fullname'   => $fullname,
                'email'      => $email,
                'mobile'     => $mobile,
                'state'      => $state,
                'district'   => $district,
                'taluka'     => $taluka,
                'ward'       => $ward,
                'updated_at' => now(),
            ]);

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Officer not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Officer updated.']);
    }

    // ============================================================
    // DELETE /api/inspection-officer/delete/{id} (District Admin only)
    // ============================================================
    public function delete(Request $request, $id)
    {
        $affected = DB::table('inspectionofficers')->where('id', $id)->delete();

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Officer not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Officer deleted.']);
    }

    // private function generateToken($id, $username, $role)
    // {
    //     $payload = [
    //         'iss'      => config('app.url'),
    //         'iat'      => time(),
    //         'exp'      => time() + (60 * 60 * 24 * 30),
    //         'id'       => $id,
    //         'username' => $username,
    //         'role'     => $role,
    //     ];

    //     return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    // }

private function generateToken($id, $username, $role, $district = '', $state = '')
{
    $payload = [
        'iss'      => config('app.url'),
        'iat'      => time(),
        'exp'      => time() + (60 * 60 * 24 * 30),
        'id'       => $id,
        'username' => $username,
        'role'     => $role,
        'district' => $district,
        'state'    => $state,
    ];

    return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
}


}
