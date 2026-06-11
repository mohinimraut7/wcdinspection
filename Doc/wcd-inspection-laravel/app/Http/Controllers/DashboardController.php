<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ============================================================
    // GET /api/dashboard/superadmin
    // ============================================================
    public function superAdmin(Request $request)
    {
        $totalStateAdmins    = DB::table('stateadmins')->count();
        $totalDistrictAdmins = DB::table('districtadmins')->count();
        $totalOfficers       = DB::table('inspectionofficers')->count();
        $totalOrgs           = DB::table('organizations')->count();
        $totalAssigned       = DB::table('surveyassignments')->where('status', 'assigned')->count();
        $totalInspected      = DB::table('surveyassignments')->where('status', 'inspected')->count();

        // Case-wise breakdown
        $casewise = DB::table('inspectionreports')
            ->select('casetype', DB::raw('COUNT(*) as count'))
            ->groupBy('casetype')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'totalStateAdmins'    => $totalStateAdmins,
                'totalDistrictAdmins' => $totalDistrictAdmins,
                'totalOfficers'       => $totalOfficers,
                'totalOrgs'           => $totalOrgs,
                'totalAssigned'       => $totalAssigned,
                'totalInspected'      => $totalInspected,
                'casewise'            => $casewise,
            ],
        ]);
    }

    // ============================================================
    // GET /api/dashboard/stateadmin
    // ============================================================
    public function stateAdmin(Request $request)
    {
        $authUser = $request->input('auth_user');

        $totalDistrictAdmins = DB::table('districtadmins')
            ->where('state', $authUser->state ?? '')
            ->count();

        $totalOfficers = DB::table('inspectionofficers')
            ->where('state', $authUser->state ?? '')
            ->count();

        $totalOrgs = DB::table('organizations')
            ->count();

        $totalInspected = DB::table('inspectionreports')->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'totalDistrictAdmins' => $totalDistrictAdmins,
                'totalOfficers'       => $totalOfficers,
                'totalOrgs'           => $totalOrgs,
                'totalInspected'      => $totalInspected,
            ],
        ]);
    }

    // ============================================================
    // GET /api/dashboard/districtadmin
    // ============================================================
    public function districtAdmin(Request $request)
    {
        $authUser = $request->input('auth_user');

        $totalOfficers = DB::table('inspectionofficers')
            ->where('createdby', $authUser->id)
            ->count();

        $totalAssigned = DB::table('surveyassignments')
            ->where('assignedby', $authUser->id)
            ->where('status', 'assigned')
            ->count();

        $totalInspected = DB::table('surveyassignments')
            ->where('assignedby', $authUser->id)
            ->where('status', 'inspected')
            ->count();

        $totalDeassigned = DB::table('surveyassignments')
            ->where('assignedby', $authUser->id)
            ->where('status', 'deassigned')
            ->count();

        $casewise = DB::table('inspectionreports as ir')
            ->join('surveyassignments as sa', 'ir.assignmentid', '=', 'sa.id')
            ->where('sa.assignedby', $authUser->id)
            ->select('ir.casetype', DB::raw('COUNT(*) as count'))
            ->groupBy('ir.casetype')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'totalOfficers'   => $totalOfficers,
                'totalAssigned'   => $totalAssigned,
                'totalInspected'  => $totalInspected,
                'totalDeassigned' => $totalDeassigned,
                'casewise'        => $casewise,
            ],
        ]);
    }

    // ============================================================
    // GET /api/dashboard/officer
    // ============================================================
    public function officer(Request $request)
    {
        $authUser = $request->input('auth_user');

        $totalAssigned = DB::table('surveyassignments')
            ->where('officerid', $authUser->id)
            ->where('status', 'assigned')
            ->count();

        $totalInspected = DB::table('inspectionreports')
            ->where('officerid', $authUser->id)
            ->count();

        $compiled = DB::table('inspectionreports')
            ->where('officerid', $authUser->id)
            ->where('status', 'compiled')
            ->count();

        $notcompiled = DB::table('inspectionreports')
            ->where('officerid', $authUser->id)
            ->where('status', 'notcompiled')
            ->count();

        $pending = DB::table('inspectionreports')
            ->where('officerid', $authUser->id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'totalAssigned'  => $totalAssigned,
                'totalInspected' => $totalInspected,
                'compiled'       => $compiled,
                'notcompiled'    => $notcompiled,
                'pending'        => $pending,
            ],
        ]);
    }
}
