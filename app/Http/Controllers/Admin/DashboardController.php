<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Atividade;
use App\Models\Inscricao;
use App\Models\Frequencia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $adminId = Auth::id();

        // 1. Cards de Estatísticas Gerais (Apenas eventos deste admin)
        $totalEventos = Evento::where('criado_por', $adminId)->count();

        $totalInscritos = Inscricao::whereHas('evento', function ($q) use ($adminId) {
            $q->where('criado_por', $adminId);
        })->count();

        // Total de certificados (frequências com presença)
        $totalCertificados = Frequencia::whereHas('atividade.evento', function ($q) use ($adminId) {
            $q->where('criado_por', $adminId);
        })->count();

        // Eventos Ativos (Data Fim > Hoje)
        $eventosAtivos = Evento::where('criado_por', $adminId)
            ->where('data_fim', '>=', now())
            ->count();

        // 2. Próximas Atividades (Agenda)
        $proximasAtividades = Atividade::whereHas('evento', function ($q) use ($adminId) {
            $q->where('criado_por', $adminId);
        })
            ->where('data_inicio', '>=', now())
            ->orderBy('data_inicio', 'asc')
            ->take(5)
            ->get();

        // 3. Top 5 Eventos Populares (Simulação de Gráfico)
        // Pega eventos com mais inscritos
        $topEventos = Evento::where('criado_por', $adminId)
            ->withCount('inscricoes')
            ->orderByDesc('inscricoes_count')
            ->take(5)
            ->get();

        // 4. Últimos Inscritos (Feed em tempo real)
        $ultimosInscritos = Inscricao::whereHas('evento', function ($q) use ($adminId) {
            $q->where('criado_por', $adminId);
        })
            ->with(['participante', 'evento'])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalEventos',
            'totalInscritos',
            'totalCertificados',
            'eventosAtivos',
            'proximasAtividades',
            'topEventos',
            'ultimosInscritos'
        ));
    }
}
