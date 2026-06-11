<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Firebase\JWT\JWT;

class OrganizationController extends Controller
{
    // ============================================================
    // POST /api/org/register
    // ============================================================
    public function register(Request $request)
    {
        $orgtype        = trim($request->input('orgtype', ''));
        $orgsector      = trim($request->input('orgsector', ''));
        $orgname        = trim($request->input('orgname', ''));
        $orgaddress     = trim($request->input('orgaddress', ''));
        $ruralurban     = trim($request->input('ruralurban', ''));
        $district       = trim($request->input('district', ''));
        $taluka         = trim($request->input('taluka', ''));
        $mahapalika     = trim($request->input('mahapalika', ''));
        $ward           = trim($request->input('ward', ''));
        $pincode        = trim($request->input('pincode', ''));
        $revenuedivision = trim($request->input('revenuedivision', ''));
        $regnotype      = trim($request->input('regnotype', ''));
        $regnovalue     = trim($request->input('regnovalue', ''));
        $concernname    = trim($request->input('concernname', ''));
        $concernmobile  = trim($request->input('concernmobile', ''));
        $concernemail   = strtolower(trim($request->input('concernemail', '')));
        $password       = $request->input('password', '');

        if (!$orgtype || !$orgname || !$concernmobile || !$password) {
            return response()->json(['success' => false, 'message' => 'Required fields missing.'], 400);
        }

        if (!preg_match('/^\d{10}$/', $concernmobile)) {
            return response()->json(['success' => false, 'message' => 'Invalid mobile number.'], 400);
        }

        $dup = DB::table('organizations')
            ->where('concernmobile', $concernmobile)
            ->first();

        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Mobile already registered.'], 409);
        }

        $id = DB::table('organizations')->insertGetId([
            'orgtype'         => $orgtype,
            'orgsector'       => $orgsector,
            'orgname'         => $orgname,
            'orgaddress'      => $orgaddress,
            'ruralurban'      => $ruralurban,
            'district'        => $district,
            'taluka'          => $taluka,
            'mahapalika'      => $mahapalika,
            'ward'            => $ward,
            'pincode'         => $pincode,
            'revenuedivision' => $revenuedivision,
            'regnotype'       => $regnotype,
            'regnovalue'      => $regnovalue,
            'concernname'     => $concernname,
            'concernmobile'   => $concernmobile,
            'concernemail'    => $concernemail,
            'password'        => Hash::make($password),
            'created_at'      => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Organization registered.', 'id' => $id], 201);
    }

    // ============================================================
    // POST /api/org/login
    // ============================================================
    public function login(Request $request)
    {
        $concernmobile = trim($request->input('concernmobile', ''));
        $password      = $request->input('password', '');

        if (!$concernmobile || !$password) {
            return response()->json(['success' => false, 'message' => 'Mobile and password required.'], 400);
        }

        $org = DB::table('organizations')->where('concernmobile', $concernmobile)->first();

        if (!$org || !Hash::check($password, $org->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $token = $this->generateToken($org->id, $org->orgname, 'organization');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'org'     => [
                'id'           => $org->id,
                'orgname'      => $org->orgname,
                'orgtype'      => $org->orgtype,
                'concernname'  => $org->concernname,
                'concernmobile'=> $org->concernmobile,
                'district'     => $org->district,
                'role'         => 'organization',
            ],
        ]);
    }

    // ============================================================
    // GET /api/org/profile (Organization only)
    // ============================================================
    public function profile(Request $request)
    {
        $authUser = $request->input('auth_user');

        $org = DB::table('organizations')
            ->where('id', $authUser->id)
            ->first();

        if (!$org) {
            return response()->json(['success' => false, 'message' => 'Organization not found.'], 404);
        }

        unset($org->password);

        return response()->json(['success' => true, 'data' => $org]);
    }

    // ============================================================
    // PUT /api/org/profile/edit (Organization only)
    // ============================================================
    public function profileEdit(Request $request)
    {
        $authUser = $request->input('auth_user');

        $affected = DB::table('organizations')
            ->where('id', $authUser->id)
            ->update([
                'orgname'         => trim($request->input('orgname', '')),
                'orgaddress'      => trim($request->input('orgaddress', '')),
                'orgsector'       => trim($request->input('orgsector', '')),
                'ruralurban'      => trim($request->input('ruralurban', '')),
                'district'        => trim($request->input('district', '')),
                'taluka'          => trim($request->input('taluka', '')),
                'mahapalika'      => trim($request->input('mahapalika', '')),
                'ward'            => trim($request->input('ward', '')),
                'pincode'         => trim($request->input('pincode', '')),
                'revenuedivision' => trim($request->input('revenuedivision', '')),
                'regnotype'       => trim($request->input('regnotype', '')),
                'regnovalue'      => trim($request->input('regnovalue', '')),
                'concernname'     => trim($request->input('concernname', '')),
            ]);

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Organization not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Profile updated.']);
    }

    // ============================================================
    // POST /api/org/survey/submit (Organization only)
    // ============================================================
    public function surveySubmit(Request $request)
    {
        $authUser = $request->input('auth_user');
        $answers  = $request->input('answers', []); // array of {questionid, answer}

        if (empty($answers)) {
            return response()->json(['success' => false, 'message' => 'Answers required.'], 400);
        }

        // Delete old responses and re-insert
        DB::table('orgsurveyresponses')->where('orgid', $authUser->id)->delete();

        $rows = [];
        foreach ($answers as $ans) {
            if (!isset($ans['questionid']) || !isset($ans['answer'])) continue;
            $rows[] = [
                'orgid'       => $authUser->id,
                'questionid'  => $ans['questionid'],
                'answer'      => $ans['answer'],
                'submittedat' => now(),
            ];
        }

        DB::table('orgsurveyresponses')->insert($rows);

        return response()->json(['success' => true, 'message' => 'Survey submitted.']);
    }

    // ============================================================
    // GET /api/org/survey/my (Organization only)
    // ============================================================
    public function mySurvey(Request $request)
    {
        $authUser = $request->input('auth_user');

        $responses = DB::table('orgsurveyresponses as r')
            ->join('surveyquestions as q', 'r.questionid', '=', 'q.id')
            ->select('q.id', 'q.srno', 'q.part', 'q.questiontext', 'q.questionmare', 'r.answer', 'r.submittedat')
            ->where('r.orgid', $authUser->id)
            ->orderBy('q.srno')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $responses->count(),
            'data'    => $responses,
        ]);
    }

    private function generateToken($id, $orgname, $role)
    {
        $payload = [
            'iss'     => config('app.url'),
            'iat'     => time(),
            'exp'     => time() + (60 * 60 * 24 * 30),
            'id'      => $id,
            'orgname' => $orgname,
            'role'    => $role,
        ];

        return JWT::encode($payload, config('app.jwt_secret'), 'HS256');
    }
}
