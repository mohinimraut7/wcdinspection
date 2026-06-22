<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrgSurveyController extends Controller
{
    // POST /api/org/survey/submit
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
            return response()->json(['success' => false, 'message' => 'Survey already submitted.'], 409);
        }

        // Insert submission
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

    // GET /api/admin/surveys (District/State/Super Admin)
    public function getAllSurveys(Request $request)
    {
        $authUser = $request->input('auth_user');

        $query = DB::table('surveysubmissions as ss')
            ->join('organizations as o', 'ss.orgid', '=', 'o.id')
            ->select(
                'ss.id as submissionid', 'ss.submittedat',
                'o.id as orgid', 'o.orgname', 'o.orgtype',
                'o.district', 'o.taluka', 'o.ward',
                'o.concernname', 'o.concernmobile'
            )
            ->orderBy('ss.submittedat', 'desc');

        // Role wise filter
        if ($authUser['role'] === 'districtadmin') {
            $query->where('o.district', $authUser['district']);
        } elseif ($authUser['role'] === 'stateadmin') {
            $query->where('o.state', $authUser['state']);
        }
        // superadmin → no filter

        $data = $query->get();

        return response()->json([
            'success' => true,
            'count'   => $data->count(),
            'data'    => $data
        ]);
    }

    // GET /api/admin/surveys/{id} (Single submission with answers)
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