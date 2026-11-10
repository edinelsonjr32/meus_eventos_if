<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Busca em tempo real de pessoas (formato do Select2) para as telas de equipe e de papéis.
 * Restrita a quem administra o evento.
 */
class BuscaPessoaController extends Controller
{
    private const POR_PAGINA = 20;

    public function __invoke(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $dados = $request->validate([
            'tipo' => ['required', 'in:usuario,participante'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $termo = trim($dados['q'] ?? '');
        $pagina = (int) ($dados['page'] ?? 1);
        $like = '%' . addcslashes($termo, '%_\\') . '%';
        $digitos = preg_replace('/\D/', '', $termo);

        // Administrador que não é geral só encontra usuários pelo e-mail completo e participantes já inscritos no próprio evento
        $geral = $request->user()->can('superadmin');

        if ($dados['tipo'] === 'usuario') {
            $consulta = User::query()->where('email', 'not like', '%@anonimizado.invalid')
                ->when(!$geral, fn ($q) => $q->where('email', $termo))
                ->when($geral && $termo !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('name')->orderBy('id');
            $itens = $consulta->offset(($pagina - 1) * self::POR_PAGINA)->limit(self::POR_PAGINA + 1)->get(['id', 'name', 'email']);
            $mapa = fn ($u) => ['id' => $u->email, 'text' => "{$u->name} ({$u->email})"];
        } else {
            $naEquipe = DB::table('equipes')->where('evento_id', $evento->id)->pluck('participante_id');
            $consulta = Participante::query()->whereNotIn('id', $naEquipe)->where('cpf', 'not like', 'ANON-%')
                ->when(!$geral, fn ($q) => $q->whereIn('id', DB::table('inscricoes')->where('evento_id', $evento->id)->select('participante_id')))
                ->when($termo !== '', fn ($q) => $q->where(function ($w) use ($like, $digitos) {
                    $w->where('nome_completo', 'like', $like)->orWhere('email', 'like', $like)->orWhere('matricula', 'like', $like);
                    if ($digitos !== '') {
                        $w->orWhere('cpf', 'like', '%' . $digitos . '%')
                            ->orWhereRaw("REPLACE(REPLACE(cpf, '.', ''), '-', '') like ?", ['%' . $digitos . '%']);
                    }
                }))
                ->orderBy('nome_completo')->orderBy('id');
            $itens = $consulta->offset(($pagina - 1) * self::POR_PAGINA)->limit(self::POR_PAGINA + 1)->get(['id', 'nome_completo', 'cpf', 'email']);
            $mapa = fn ($p) => ['id' => $p->cpf, 'text' => "{$p->nome_completo} — CPF {$p->cpf}"];
        }

        return response()->json([
            'results' => $itens->take(self::POR_PAGINA)->map($mapa)->values(),
            'pagination' => ['more' => $itens->count() > self::POR_PAGINA],
        ]);
    }
}
