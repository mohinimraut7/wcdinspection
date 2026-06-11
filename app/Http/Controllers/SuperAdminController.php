<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;
use Carbon\Carbon;

class SuperAdminController extends Controller
{
    // ============================================================
    // POST /api/super-admin/add
    // ============================================================
    public function add(Request $request)
    {
        $fullname = trim($request->input('fullname', ''));
        $username = strtolower(trim($request->input('username', '')));
        $email    = strtolower(trim($request->input('email', '')));
        $mobile   = trim($request->input('mobile', ''));
        $password = $request->input('password', '');

        if (!$fullname || !$username || !$mobile || !$password) {
            return response()->json(['success' => false, 'message' => 'All fields required.'], 400);
        }

        if (!preg_match('/^\d{10}$/', $mobile)) {
            return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 400);
        }

        $dup = DB::table('superadmins')
            ->where('username', $username)
            ->orWhere('mobile', $mobile)
            ->first();

        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Username or mobile already exists.'], 409);
        }

        $hashedPassword = Hash::make($password);

        $id = DB::table('superadmins')->insertGetId([
            'fullname'   => $fullname,
            'username'   => $username,
            'email'      => $email,
            'mobile'     => $mobile,
            'password'   => $hashedPassword,
            // 'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Super admin added.',
            'id'      => $id,
        ], 201);
    }

    // ============================================================
    // POST /api/super-admin/login
    // ============================================================
    public function login(Request $request)
    {
        $username = strtolower(trim($request->input('username', '')));
        $password = $request->input('password', '');

        if (!$username || !$password) {
            return response()->json(['success' => false, 'message' => 'Username and password required.'], 400);
        }

        $user = DB::table('superadmins')->where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['success' => false, 'message' => 'Account is deactivated.'], 403);
        }

        $token = $this->generateToken($user->id, $user->username, 'superadmin');

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
                'role'     => 'superadmin',
            ],
        ]);
    }

    // ============================================================
    // POST /api/super-admin/send-otp
    // ============================================================
    public function sendOtp(Request $request)
    {
        $mobile = trim($request->input('mobile', ''));

        if (!$mobile) {
            return response()->json(['success' => false, 'message' => 'Mobile required.'], 400);
        }

        $user = DB::table('superadmins')->where('mobile', $mobile)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Mobile not found.'], 404);
        }

        $otp = rand(100000, 999999);
        $expiry = Carbon::now()->addMinutes(10);

        DB::table('superadmins')
            ->where('id', $user->id)
            ->update(['otp' => $otp, 'otp_expires_at' => $expiry, 'updated_at' => now()]);

        // TODO: Send OTP via SMS (WhatsApp API / SMS gateway)
        // For now returning OTP in response (remove in production)

        return response()->json([
            'success' => true,
            'message' => 'OTP sent.',
            'otp'     => $otp, // Remove in production
        ]);
    }

    // ============================================================
    // POST /api/super-admin/verify-otp
    // ============================================================
    public function verifyOtp(Request $request)
    {
        $mobile = trim($request->input('mobile', ''));
        $otp    = $request->input('otp', '');

        if (!$mobile || !$otp) {
            return response()->json(['success' => false, 'message' => 'Mobile and OTP required.'], 400);
        }

        $user = DB::table('superadmins')->where('mobile', $mobile)->first();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Mobile not found.'], 404);
        }

        if ($user->otp != $otp) {
            return response()->json(['success' => false, 'message' => 'Invalid OTP.'], 400);
        }

        if (Carbon::now()->isAfter($user->otp_expires_at)) {
            return response()->json(['success' => false, 'message' => 'OTP expired.'], 400);
        }

        // Clear OTP
        DB::table('superadmins')
            ->where('id', $user->id)
            ->update(['otp' => null, 'otp_expires_at' => null, 'updated_at' => now()]);

        $token = $this->generateToken($user->id, $user->username, 'superadmin');

        return response()->json([
            'success' => true,
            'message' => 'OTP verified.',
            'token'   => $token,
        ]);
    }

    // ============================================================
    // GET /api/super-admin/get
    // ============================================================
    public function get(Request $request)
    {
        $admins = DB::table('superadmins')
            ->select('id', 'fullname', 'username', 'email', 'mobile', 'is_active', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $admins->count(),
            'data'    => $admins,
        ]);
    }

    // ============================================================
    // PUT /api/super-admin/edit/{id}
    // ============================================================
    public function edit(Request $request, $id)
    {
        $fullname  = trim($request->input('fullname', ''));
        $email     = strtolower(trim($request->input('email', '')));
        $mobile    = trim($request->input('mobile', ''));
        $is_active = $request->input('is_active', 1);

        $affected = DB::table('superadmins')
            ->where('id', $id)
            ->update([
                'fullname'   => $fullname,
                'email'      => $email,
                'mobile'     => $mobile,
                'is_active'  => $is_active,
                'updated_at' => now(),
            ]);

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Super admin not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Super admin updated.']);
    }

    // ============================================================
    // DELETE /api/super-admin/delete/{id}
    // ============================================================
    public function delete(Request $request, $id)
    {
        // Minimum 1 super admin must remain
        $count = DB::table('superadmins')->count();

        if ($count <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the last super admin.',
            ], 400);
        }

        $affected = DB::table('superadmins')->where('id', $id)->delete();

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Super admin not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Super admin deleted.']);
    }

    // ============================================================
    // Helper — Generate JWT Token (same as Node.js jwt.sign)
    // ============================================================
    private function generateToken($id, $username, $role)
    {
        $payload = [
            'iss'      => config('app.url'),
            'iat'      => time(),
            'exp'      => time() + (60 * 60 * 24 * 30), // 30 days (same as JWT_TTL in .env)
            'id'       => $id,
            'username' => $username,
            'role'     => $role,
        ];

        return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    }
}
