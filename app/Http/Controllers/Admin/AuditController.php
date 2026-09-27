<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['entity' => 'nullable|in:user,product,supply', 'entity_id' => 'nullable|string|max:64']);
        $query = DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.user_id')
            ->select('audit_events.*', 'users.name as actor')->orderByDesc('audit_events.id');
        if (! empty($data['entity'])) {
            $query->where('entity_type', $data['entity']);
        }
        if (! empty($data['entity_id'])) {
            $query->where('entity_id', $data['entity_id']);
        }

        return view('admin.audit', ['events' => $query->paginate(30)->withQueryString()]);
    }
}
