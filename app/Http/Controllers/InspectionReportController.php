<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionReportController extends Controller
{
    // ============================================================
    // GET /api/officer/assignments (Officer only)
    // ============================================================
    public function getMyAssignments(Request $request)
    {
        $authUser = $request->input('auth_user');

        $assignments = DB::table('surveyassignments as sa')
            ->join('organizations as o', 'sa.orgid', '=', 'o.id')
            ->select(
                'sa.id as assignmentid', 'sa.status', 'sa.assignedat',
                'o.id as orgid', 'o.orgname', 'o.orgtype', 'o.orgaddress',
                'o.district', 'o.taluka', 'o.ward', 'o.concernname', 'o.concernmobile'
            )
            // ->where('sa.officerid', $authUser->id)
            ->where('sa.officerid', $authUser['id'])  // -> ऐवजी ['id']
            ->where('sa.status', 'assigned')
            ->orderBy('sa.assignedat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $assignments->count(),
            'data'    => $assignments,
        ]);
    }

    // ============================================================
    // POST /api/officer/report/submit (Officer — Case 1 & 2)
    // ============================================================
    public function submitReport(Request $request)
    {
        $authUser             = $request->input('auth_user');
        $assignmentid         = $request->input('assignmentid');
        $casetype             = $request->input('casetype');
        $status               = $request->input('status');
        $officername          = trim($request->input('officername', ''));
        $officerdesignation   = trim($request->input('officerdesignation', ''));
        $officersignature     = $request->input('officersignature', '');
        $concernname          = trim($request->input('concernname', ''));
        $concernsignature     = $request->input('concernsignature', '');
        $finalremark          = trim($request->input('finalremark', ''));
        $latitude             = $request->input('latitude');
        $longitude            = $request->input('longitude');
        $remarks              = $request->input('remarks', []); // Case 2 question-wise

        if (!$assignmentid || !$casetype || !$status || !$officername || !$officerdesignation) {
            return response()->json(['success' => false, 'message' => 'Required fields missing.'], 400);
        }

        if (!$latitude || !$longitude) {
            return response()->json(['success' => false, 'message' => 'Latitude and longitude required.'], 400);
        }

        // Check assignment belongs to this officer
        $assignment = DB::table('surveyassignments')
            ->where('id', $assignmentid)
            ->where('officerid', $authUser->id)
            ->first();

        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'Assignment not found.'], 404);
        }

        $orgid = $assignment->orgid;

        // Insert report
        $reportId = DB::table('inspectionreports')->insertGetId([
            'assignmentid'       => $assignmentid,
            'orgid'              => $orgid,
            'officerid'          => $authUser->id,
            'casetype'           => $casetype,
            'status'             => $status,
            'officername'        => $officername,
            'officerdesignation' => $officerdesignation,
            'officersignature'   => $officersignature,
            'concernname'        => $concernname,
            'concernsignature'   => $concernsignature,
            'finalremark'        => $finalremark,
            'latitude'           => $latitude,
            'longitude'          => $longitude,
            'submittedat'        => now(),
        ]);

        // Case 2 — Insert question-wise remarks
        if ($casetype === 'case2' && !empty($remarks)) {
            $remarkRows = [];
            foreach ($remarks as $r) {
                if (!isset($r['questionid'])) continue;
                $remarkRows[] = [
                    'reportid'   => $reportId,
                    'questionid' => $r['questionid'],
                    'originalans'=> $r['originalans'] ?? null,
                    'editedans'  => $r['editedans'] ?? null,
                    'remark'     => $r['remark'] ?? null,
                ];
            }
            if (!empty($remarkRows)) {
                DB::table('inspectionremarks')->insert($remarkRows);
            }
        }

        // Update assignment status to inspected
        DB::table('surveyassignments')
            ->where('id', $assignmentid)
            ->update(['status' => 'inspected']);

        return response()->json(['success' => true, 'message' => 'Report submitted.', 'reportid' => $reportId], 201);
    }

    // ============================================================
    // GET /api/officer/report/get (Officer only)
    // ============================================================
    public function getMyReports(Request $request)
    {
        $authUser = $request->input('auth_user');

        $reports = DB::table('inspectionreports as ir')
            ->join('organizations as o', 'ir.orgid', '=', 'o.id')
            ->select(
                'ir.id', 'ir.casetype', 'ir.status', 'ir.finalremark',
                'ir.latitude', 'ir.longitude', 'ir.submittedat',
                'o.orgname', 'o.orgtype', 'o.district', 'o.taluka'
            )
            // ->where('ir.officerid', $authUser->id)
            ->where('ir.officerid', $authUser['id'])
            ->orderBy('ir.submittedat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $reports->count(),
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // PUT /api/officer/report/reinspect/{id} (Officer — Case 4)
    // ============================================================
    public function reinspectReport(Request $request, $id)
    {
        $authUser   = $request->input('auth_user');
        $status     = $request->input('status');        // 'compiled' or 'rejected'
        $finalremark= trim($request->input('finalremark', ''));
        $remarks    = $request->input('remarks', []);

        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status required.'], 400);
        }

        $report = DB::table('inspectionreports')
            ->where('id', $id)
            // ->where('officerid', $authUser->id)
            ->where('officerid', $authUser['id'])

            ->first();

        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }

        DB::table('inspectionreports')
            ->where('id', $id)
            ->update([
                'casetype'    => 'case4',
                'status'      => $status,
                'finalremark' => $finalremark,
                'submittedat' => now(),
            ]);

        // Insert re-inspection remarks if any
        if (!empty($remarks)) {
            $remarkRows = [];
            foreach ($remarks as $r) {
                if (!isset($r['questionid'])) continue;
                $remarkRows[] = [
                    'reportid'    => $id,
                    'questionid'  => $r['questionid'],
                    'originalans' => $r['originalans'] ?? null,
                    'editedans'   => $r['editedans'] ?? null,
                    'remark'      => $r['remark'] ?? null,
                ];
            }
            if (!empty($remarkRows)) {
                DB::table('inspectionremarks')->insert($remarkRows);
            }
        }

        return response()->json(['success' => true, 'message' => 'Re-inspection submitted.']);
    }
}
