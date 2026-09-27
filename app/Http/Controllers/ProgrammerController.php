<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;
use App\Services\ProgrammerPanelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgrammerController extends Controller
{
    public function panel(ProgrammerPanelService $service)
    {
        $metrics = $service->getSystemMetrics();
        $recentAdjustments = InventoryLog::where('type', 'programmer_adjustment')
            ->with('supply', 'user')->latest('created_at')->take(20)->get();

        return view('programmer.panel', compact('metrics', 'recentAdjustments'));
    }

    public function purge(ProgrammerPanelService $service)
    {
        $results = $service->purgeTemporaryData();
        return back()->with('success', 'Purga completada: ' . implode(' | ', $results));
    }

    public function killProcesses(ProgrammerPanelService $service)
    {
        $results = $service->killStalledProcesses();
        return back()->with('success', 'Procesos limpiados: ' . implode(' | ', $results));
    }

    public function repairIntegrity(ProgrammerPanelService $service)
    {
        $results = $service->repairInventoryIntegrity(Auth::id());
        $repaired = collect($results)->where('repaired', true)->count();
        $total = collect($results)->where('needs_repair', true)->count();

        if ($total === 0) {
            return back()->with('success', '✅ Todos los stocks son consistentes. No se requieren ajustes.');
        }

        return view('programmer.integrity-results', ['results' => $results]);
    }

    public function integrityDryRun(ProgrammerPanelService $service)
    {
        $results = $service->repairInventoryIntegrity(Auth::id(), dryRun: true);
        return view('programmer.integrity-results', ['results' => $results]);
    }
}
