<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgSurveyController extends Controller
{
    // ============================================================
    // POST /api/org/survey/submit
    // ============================================================
    public function submit(Request $request)
    {
        $authUser = $request->input('auth_user');
        $answers  = $request->input('answers', []);

        if (empty($answers)) {
            return response()->json(['success' => false, 'message' => 'Answers required.'], 400);
        }

        // Already submitted check
        $existing = DB::table('surveysubmissions')
            ->where('orgid', $authUser['id'])
            ->first();

        if ($existing) {
            // ── Allow re-submit ONLY if the latest inspection report says
            //    rejected / notcompiled. Otherwise block re-submission. ──
            $report = DB::table('inspectionreports')
                ->where('orgid', $authUser['id'])
                ->orderBy('id', 'desc')
                ->first();

            $reportStatus = $report->finalstatus ?? ($report->status ?? null);

            if (!$report || !in_array($reportStatus, ['rejected', 'notcompiled'])) {
                return response()->json(['success' => false, 'message' => 'Survey already submitted.'], 409);
            }

            // Edit allowed — remove old submission + its answers, then re-insert fresh
            DB::table('surveyanswers')->where('submissionid', $existing->id)->delete();
            DB::table('surveysubmissions')->where('id', $existing->id)->delete();
        }

        // Insert submissio
        $submissionId = DB::table('surveysubmissions')->insertGetId([
            'orgid'       => $authUser['id'],
            'submittedat' => now(),
            'created_at'  => now(),
        ]);

        // Insert answers
        $rows = [];
        foreach ($answers as $a) {
            if (!isset($a['questionid'])) continue;
            $rows[] = [
                'submissionid' => $submissionId,
                'questionid'   => $a['questionid'],
                'answer'       => $a['answer'],
            ];
        }
        if (!empty($rows)) {
            DB::table('surveyanswers')->insert($rows);
        }

        return response()->json([
            'success'      => true,
            'message'      => 'Survey submitted.',
            'submissionid' => $submissionId
        ], 201);
    }

    // ============================================================
    // GET /api/org/survey/status (Organization only)
    // Tells the mobile/web app whether this org has already submitted,
    // what their answers were, whether they're allowed to edit
    // (only when Inspection Officer marked it rejected/notcompiled),
    // and — if editable — exactly WHICH questions were flagged
    // non-compliant along with the officer's comment for each.
    // ============================================================
    public function myStatus(Request $request)
    {
        $authUser = $request->input('auth_user');
        $orgid    = $authUser['id'];

        $submission = DB::table('surveysubmissions')
            ->where('orgid', $orgid)
            ->first();

        // ── Not submitted yet — normal editable form should show ──
        if (!$submission) {
            return response()->json([
                'success'   => true,
                'submitted' => false,
            ]);
        }

        // ── Fetch this submission's answers ──
        $answerRows = DB::table('surveyanswers')
            ->where('submissionid', $submission->id)
            ->get(['questionid', 'answer']);

        $answers = [];
        foreach ($answerRows as $a) {
            $answers[$a->questionid] = $a->answer;
        }

        // ── Latest inspection report for this org (if any) ──
        $report = DB::table('inspectionreports')
            ->where('orgid', $orgid)
            ->orderBy('id', 'desc')
            ->first();

        $reportStatus = null;
        $reviewround  = null;
        $canEdit      = false;
        $editableQuestions = [];   // { questionid: { comment } } — only non-compliant ones

        if ($report) {
            $reportStatus = $report->finalstatus ?? $report->status;
            $reviewround  = $report->reviewround;
            // Only rejected / notcompiled survey can be edited & resubmitted
            $canEdit = in_array($reportStatus, ['rejected', 'notcompiled']);

            if ($canEdit) {
                // Pull the questions THIS report flagged as "no" — round1 uses
                // originalans, round2 uses editedans — plus the officer's comment.
                $remarks = DB::table('inspectionremarks')
                    ->where('reportid', $report->id)
                    ->where(function ($q) {
                        $q->where('originalans', 'no')
                          ->orWhere('editedans', 'no');
                    })
                    ->get(['questionid', 'remark', 'comment']);

                foreach ($remarks as $r) {
                    $editableQuestions[$r->questionid] = [
                        'comment' => $r->comment ?: $r->remark ?: null,
                    ];
                }
            }
        }

        return response()->json([
            'success'            => true,
            'submitted'          => true,
            'submissionid'       => $submission->id,
            'submittedat'        => $submission->submittedat,
            'answers'            => $answers,            // {questionid: "yes"/"no"} — full previous answers
            'reportExists'       => (bool) $report,
            'status'             => $reportStatus,        // null | pending | compiled | notcompiled | rejected
            'reviewround'        => $reviewround,
            'canEdit'            => $canEdit,             // true only for rejected/notcompiled
            'editableQuestions'  => $editableQuestions,   // {questionid: {comment}} — ONLY these should be unlocked
        ]);
    }

    // ============================================================
    // GET /api/admin/surveys (District/State/Super Admin)
    // ============================================================
    public function getAllSurveys(Request $request)
    {
        $authUser = $request->input('auth_user');

        $role     = $authUser['role']     ?? 'superadmin';
        $district = $authUser['district'] ?? '';
        $state    = $authUser['state']    ?? '';
        $id       = $authUser['id']       ?? null;

        $query = DB::table('surveysubmissions as ss')
            ->join('organizations as o', 'ss.orgid', '=', 'o.id')
            // ── Latest inspectionreport चा finalstatus ──
            ->leftJoin(DB::raw('(
                SELECT orgid, finalstatus, reviewround
                FROM inspectionreports
                WHERE id IN (
                    SELECT MAX(id) FROM inspectionreports GROUP BY orgid
                )
            ) as ir'), 'ss.orgid', '=', 'ir.orgid')
            ->select(
                'ss.id as submissionid', 'ss.submittedat',
                'o.id as orgid', 'o.orgname', 'o.orgtype',
                'o.district', 'o.taluka', 'o.ward',
                'o.concernname', 'o.concernmobile',
                'ir.finalstatus', 'ir.reviewround'
            )
            ->orderBy('ss.submittedat', 'desc');

        if ($role === 'districtadmin' && $district) {
            $query->where('o.district', $district);
        } elseif ($role === 'stateadmin' && $state) {
            $query->where('o.state', $state);
        } elseif ($role === 'inspectionofficer' && $id) {
            $query->join('surveyassignments as sa', 'ss.orgid', '=', 'sa.orgid')
                  ->where('sa.officerid', $id);
        }

        // superadmin → no filter

        $data = $query->get();

        return response()->json([
            'success' => true,
            'count'   => $data->count(),
            'data'    => $data
        ]);
    }

    // ============================================================
    // GET /api/admin/surveys/{id} (Single submission with answers)
    // ============================================================
    public function getSurveyDetail(Request $request, $submissionid)
    {
        $submission = DB::table('surveysubmissions as ss')
            ->join('organizations as o', 'ss.orgid', '=', 'o.id')
            ->select(
                'ss.id', 'ss.submittedat',
                'o.orgname', 'o.orgtype', 'o.district',
                'o.taluka', 'o.ward', 'o.concernname', 'o.concernmobile'
            )
            ->where('ss.id', $submissionid)
            ->first();

        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $answers = DB::table('surveyanswers')
            ->where('submissionid', $submissionid)
            ->get(['questionid', 'answer']);

        // { questionid => answer } map
        $map = [];
        foreach ($answers as $a) {
            $map[$a->questionid] = $a->answer;
        }

        return response()->json([
            'success'    => true,
            'submission' => $submission,
            'answers'    => $map,
        ]);
    }
}