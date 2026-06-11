<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyAssignmentController extends Controller
{
    // ============================================================
    // POST /api/assignment/assign (District Admin only)
    // ============================================================
    public function assign(Request $request)
    {
        $authUser  = $request->input('auth_user');
        $orgid     = $request->input('orgid');
        $officerid = $request->input('officerid');

        if (!$orgid || !$officerid) {
            return response()->json(['success' => false, 'message' => 'orgid and officerid required.'], 400);
        }

        // Check org exists
        $org = DB::table('organizations')->where('id', $orgid)->first();
        if (!$org) {
            return response()->json(['success' => false, 'message' => 'Organization not found.'], 404);
        }

        // Check officer exists
        $officer = DB::table('inspectionofficers')->where('id', $officerid)->first();
        if (!$officer) {
            return response()->json(['success' => false, 'message' => 'Officer not found.'], 404);
        }

        // Check if already assigned
        $existing = DB::table('surveyassignments')
            ->where('orgid', $orgid)
            ->where('status', 'assigned')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Organization already assigned.'], 409);
        }

        $id = DB::table('surveyassignments')->insertGetId([
            'orgid'      => $orgid,
            'officerid'  => $officerid,
            'assignedby' => $authUser->id,
            'status'     => 'assigned',
            'assignedat' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Assignment created.', 'id' => $id], 201);
    }

    // ============================================================
    // PUT /api/assignment/deassign/{id} (District Admin only)
    // ============================================================
    public function deassign(Request $request, $id)
    {
        $affected = DB::table('surveyassignments')
            ->where('id', $id)
            ->where('status', 'assigned')
            ->update(['status' => 'deassigned']);

        if (!$affected) {
            return response()->json(['success' => false, 'message' => 'Assignment not found or already deassigned.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Assignment deassigned.']);
    }

    // ============================================================
    // GET /api/assignment/get (District Admin only)
    // ============================================================
    public function get(Request $request)
    {
        $authUser = $request->input('auth_user');

        $assignments = DB::table('surveyassignments as sa')
            ->join('organizations as o', 'sa.orgid', '=', 'o.id')
            ->join('inspectionofficers as io', 'sa.officerid', '=', 'io.id')
            ->select(
                'sa.id', 'sa.status', 'sa.assignedat',
                'o.id as orgid', 'o.orgname', 'o.orgtype', 'o.district', 'o.taluka',
                'io.id as officerid', 'io.fullname as officername', 'io.mobile as officermobile'
            )
            ->where('sa.assignedby', $authUser->id)
            ->orderBy('sa.assignedat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $assignments->count(),
            'data'    => $assignments,
        ]);
    }
}
