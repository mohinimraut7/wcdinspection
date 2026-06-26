<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InspectionReportController extends Controller
{
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
            ->where('sa.officerid', $authUser['id'])
            ->where('sa.status', 'assigned')
            ->orderBy('sa.assignedat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $assignments->count(),
            'data'    => $assignments,
        ]);
    }

    public function quickReview(Request $request)
    {
        $authUser           = $request->input('auth_user');
        $orgid              = $request->input('orgid');
        $casetype           = $request->input('casetype');
        $officername        = trim($request->input('officername', ''));
        $officerdesignation = trim($request->input('officerdesignation', ''));
        $finalremark        = trim($request->input('finalremark', ''));
        $latitude           = $request->input('latitude',  null);
        $longitude          = $request->input('longitude', null);
        $questionReviews    = $request->input('questionReviews', []);

        if (!$orgid || !$casetype || !$officername || !$officerdesignation) {
            return response()->json(['success' => false, 'message' => 'Required fields missing.'], 400);
        }

        if (empty($questionReviews)) {
            return response()->json(['success' => false, 'message' => 'Question reviews required.'], 400);
        }

        $assignment = DB::table('surveyassignments')
            ->where('orgid', $orgid)
            ->where('officerid', $authUser['id'])
            ->whereIn('status', ['assigned', 'inspected'])
            ->orderBy('assignedat', 'desc')
            ->first();

        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'No active assignment found.'], 404);
        }

        $existingReports = DB::table('inspectionreports')
            ->where('orgid', $orgid)
            ->where('officerid', $authUser['id'])
            ->orderBy('created_at', 'asc')
            ->get();

        $reviewround = $existingReports->count() + 1;

        $hasAnyNo = collect($questionReviews)->contains(fn($q) => ($q['answer'] ?? '') === 'no');

        if ($reviewround === 1) {
            $finalStatus = $hasAnyNo ? 'notcompiled' : 'compiled';
        } else {
            $finalStatus = $hasAnyNo ? 'rejected' : 'compiled';
        }

        $reportId = DB::table('inspectionreports')->insertGetId([
            'assignmentid'       => $assignment->id,
            'orgid'              => $orgid,
            'officerid'          => $authUser['id'],
            'casetype'           => $casetype,
            'status'             => $finalStatus,
            'officername'        => $officername,
            'officerdesignation' => $officerdesignation,
            'officersignature'   => '',
            'concernname'        => '',
            'concernsignature'   => '',
            'finalremark'        => $finalremark,
            'latitude'           => $latitude,
            'longitude'          => $longitude,
            'reviewround'        => $reviewround,
            'finalstatus'        => $finalStatus,
            'submittedat'        => now(),
        ]);

        $remarkRows = [];
        foreach ($questionReviews as $q) {
            if (!isset($q['questionid'])) continue;
            $remarkRows[] = [
                'reportid'    => $reportId,
                'questionid'  => $q['questionid'],
                'originalans' => $reviewround === 1 ? ($q['answer'] ?? null) : null,
                'editedans'   => $reviewround === 2 ? ($q['answer'] ?? null) : null,
                'remark'      => $q['comment'] ?? null,
                'comment'     => $q['comment'] ?? null,
            ];
        }
        if (!empty($remarkRows)) {
            DB::table('inspectionremarks')->insert($remarkRows);
        }

        $newStatus = ($finalStatus === 'compiled' || $finalStatus === 'rejected') ? 'completed' : 'inspected';
        DB::table('surveyassignments')
            ->where('id', $assignment->id)
            ->update(['status' => $newStatus]);

        if ($finalStatus === 'compiled') {
            $message = 'Survey marked as Complied. Final submission done.';
        } elseif ($finalStatus === 'rejected') {
            $message = 'Survey Permanently Rejected.';
        } else {
            $message = 'Review submitted. 15-day notice issued for non-compliant questions.';
        }

        return response()->json([
            'success'     => true,
            'message'     => $message,
            'reportid'    => $reportId,
            'finalstatus' => $finalStatus,
            'reviewround' => $reviewround,
        ], 201);
    }

    public function getReviewRound(Request $request)
    {
        $authUser = $request->input('auth_user');
        $orgid    = $request->query('orgid');

        if (!$orgid) {
            return response()->json(['success' => false, 'message' => 'orgid required.'], 400);
        }

        $reports = DB::table('inspectionreports')
            ->where('orgid', $orgid)
            ->where('officerid', $authUser['id'])
            ->orderBy('created_at', 'asc')
            ->get();

        $round = $reports->count() + 1;

        $noQuestionIds = [];
        if ($reports->count() >= 1) {
            $firstReport = $reports->first();
            $noQuestionIds = DB::table('inspectionremarks')
                ->where('reportid', $firstReport->id)
                ->where('originalans', 'no')
                ->pluck('questionid')
                ->toArray();
        }

        return response()->json([
            'success'       => true,
            'reviewround'   => $round,
            'noQuestionIds' => $noQuestionIds,
            'finalstatus'   => $reports->last()?->finalstatus ?? null,
        ]);
    }

    public function getMyReports(Request $request)
    {
        $authUser = $request->input('auth_user');

        $reports = DB::table('inspectionreports as ir')
            ->join('organizations as o', 'ir.orgid', '=', 'o.id')
            ->select(
                'ir.id', 'ir.casetype', 'ir.status', 'ir.finalremark',
                'ir.reviewround', 'ir.finalstatus',
                'ir.latitude', 'ir.longitude', 'ir.submittedat',
                'o.orgname', 'o.orgtype', 'o.district', 'o.taluka'
            )
            ->where('ir.officerid', $authUser['id'])
            ->orderBy('ir.submittedat', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count'   => $reports->count(),
            'data'    => $reports,
        ]);
    }

    public function submitReport(Request $request)
    {
        $authUser           = $request->input('auth_user');
        $assignmentid       = $request->input('assignmentid');
        $casetype           = $request->input('casetype');
        $status             = $request->input('status');
        $officername        = trim($request->input('officername', ''));
        $officerdesignation = trim($request->input('officerdesignation', ''));
        $officersignature   = $request->input('officersignature', '');
        $concernname        = trim($request->input('concernname', ''));
        $concernsignature   = $request->input('concernsignature', '');
        $finalremark        = trim($request->input('finalremark', ''));
        $latitude           = $request->input('latitude');
        $longitude          = $request->input('longitude');
        $remarks            = $request->input('remarks', []);

        if (!$assignmentid || !$casetype || !$status || !$officername || !$officerdesignation) {
            return response()->json(['success' => false, 'message' => 'Required fields missing.'], 400);
        }

        $assignment = DB::table('surveyassignments')
            ->where('id', $assignmentid)
            ->where('officerid', $authUser['id'])
            ->first();

        if (!$assignment) {
            return response()->json(['success' => false, 'message' => 'Assignment not found.'], 404);
        }

        $orgid = $assignment->orgid;

        $reportId = DB::table('inspectionreports')->insertGetId([
            'assignmentid'       => $assignmentid,
            'orgid'              => $orgid,
            'officerid'          => $authUser['id'],
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

        if ($casetype === 'case2' && !empty($remarks)) {
            $remarkRows = [];
            foreach ($remarks as $r) {
                if (!isset($r['questionid'])) continue;
                $remarkRows[] = [
                    'reportid'    => $reportId,
                    'questionid'  => $r['questionid'],
                    'originalans' => $r['originalans'] ?? null,
                    'editedans'   => $r['editedans']   ?? null,
                    'remark'      => $r['remark']       ?? null,
                ];
            }
            if (!empty($remarkRows)) {
                DB::table('inspectionremarks')->insert($remarkRows);
            }
        }

        DB::table('surveyassignments')
            ->where('id', $assignmentid)
            ->update(['status' => 'inspected']);

        return response()->json(['success' => true, 'message' => 'Report submitted.', 'reportid' => $reportId], 201);
    }

    public function reinspectReport(Request $request, $id)
    {
        $authUser    = $request->input('auth_user');
        $status      = $request->input('status');
        $finalremark = trim($request->input('finalremark', ''));
        $remarks     = $request->input('remarks', []);

        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status required.'], 400);
        }

        $report = DB::table('inspectionreports')
            ->where('id', $id)
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

        if (!empty($remarks)) {
            $remarkRows = [];
            foreach ($remarks as $r) {
                if (!isset($r['questionid'])) continue;
                $remarkRows[] = [
                    'reportid'    => $id,
                    'questionid'  => $r['questionid'],
                    'originalans' => $r['originalans'] ?? null,
                    'editedans'   => $r['editedans']   ?? null,
                    'remark'      => $r['remark']       ?? null,
                ];
            }
            if (!empty($remarkRows)) {
                DB::table('inspectionremarks')->insert($remarkRows);
            }
        }

        return response()->json(['success' => true, 'message' => 'Re-inspection submitted.']);
    }
}