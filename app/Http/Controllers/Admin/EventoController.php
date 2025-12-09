<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Assinatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventoController extends Controller
{
    /**
     * Lista os eventos do administrador logado.
     */
    public function index()
    {
        $eventos = Evento::where('criado_por', Auth::id())
            ->orderBy('data_inicio', 'desc')
            ->paginate(10);

        return view('admin.eventos.index', compact('eventos'));
    }

    /**
     * Exibe o formulário de criação.
     */
    public function create()
    {
        return view('admin.eventos.create');
    }

    /**
     * Armazena um novo evento.
     */
    public function store(Request $request)
    {
        // 1. Validação Completa
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'local' => 'required|string',
            'cor_fundo' => 'nullable|string',

            // Geo-fencing
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'raio_permitido' => 'nullable|integer|min:10',
        ]);

        $slug = Str::slug($validated['titulo']) . '-' . date('Y');

        // 2. Criação do Registro
        Evento::create([
            'titulo' => $validated['titulo'],
            'slug' => $slug,
            'descricao' => $validated['descricao'],
            'data_inicio' => $validated['data_inicio'],
            'data_fim' => $validated['data_fim'],
            'local' => $validated['local'],

            // Coordenadas
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'raio_permitido' => $request->raio_permitido ?? 300,

            'criado_por' => Auth::id(),
            'configuracoes' => [
                'cor_fundo' => $request->input('cor_fundo', '#10b981'),
            ]
        ]);

        return redirect()->route('admin.eventos.index')
            ->with('success', 'Evento criado com sucesso!');
    }

    /**
     * Exibe o painel de gestão do evento.
     */
    public function show(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        return view('admin.eventos.show', compact('evento'));
    }

    /**
     * Exibe o formulário de edição (com abas de Certificado/Assinaturas).
     */
    public function edit(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        return view('admin.eventos.edit', compact('evento'));
    }

    /**
     * Atualiza o evento, layout, textos e assinaturas.
     */
    public function update(Request $request, Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        // 1. Validação Robusta
        $request->validate([
            // Dados Gerais
            'titulo' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date',
            'local' => 'required|string',

            // Arquivos de Layout
            'imagem_fundo' => 'nullable|image|max:4096', // Max 4MB
            'imagem_brasao' => 'nullable|image|max:2048',

            // Textos do Certificado
            'cert_cabecalho' => 'nullable|string',
            'cert_corpo' => 'nullable|string',
            'cert_rodape' => 'nullable|string',

            // Assinaturas (Array)
            'assinaturas.*.nome' => 'nullable|string',
            'assinaturas.*.cargo' => 'nullable|string',
            'assinaturas.*.arquivo' => 'nullable|image|max:2048',
        ]);

        // =========================================================
        // A. REMOÇÃO DE ASSINATURAS (Processar antes de salvar)
        // =========================================================
        if ($request->filled('assinaturas_para_remover')) {
            $idsRaw = explode(',', $request->input('assinaturas_para_remover'));
            $idsParaRemover = array_filter($idsRaw, 'is_numeric'); // Segurança: remove vazios/inválidos

            if (!empty($idsParaRemover)) {
                // Remove os arquivos físicos das assinaturas deletadas para limpar storage
                $assinaturasDeletadas = Assinatura::whereIn('id', $idsParaRemover)->get();
                foreach($assinaturasDeletadas as $ass) {
                    if($ass->arquivo_assinatura) Storage::disk('public')->delete($ass->arquivo_assinatura);
                }
                Assinatura::destroy($idsParaRemover);
            }
        }

        // =========================================================
        // B. ATUALIZAÇÃO DE DADOS BÁSICOS E TEXTOS
        // =========================================================
        $dadosUpdate = $request->only([
            'titulo', 'descricao', 'data_inicio', 'data_fim',
            'local', 'latitude', 'longitude', 'raio_permitido',
            'cert_cabecalho', 'cert_corpo', 'cert_rodape'
        ]);

        // Atualiza JSON de Configurações
        $configs = $evento->configuracoes ?? [];
        $configs['cor_fundo'] = $request->input('cor_fundo', '#10b981');
        $evento->configuracoes = $configs;

        // =========================================================
        // C. GESTÃO DE IMAGENS DO LAYOUT (Upload e Remoção)
        // =========================================================

        // 1. Fundo
        if ($request->boolean('remover_fundo')) {
            if ($evento->caminho_fundo) Storage::disk('public')->delete($evento->caminho_fundo);
            $dadosUpdate['caminho_fundo'] = null;
        }
        if ($request->hasFile('imagem_fundo')) {
            if ($evento->caminho_fundo && !isset($dadosUpdate['caminho_fundo'])) {
                Storage::disk('public')->delete($evento->caminho_fundo);
            }
            $dadosUpdate['caminho_fundo'] = $request->file('imagem_fundo')->store('eventos/fundos', 'public');
        }

        // 2. Brasão
        if ($request->boolean('remover_brasao')) {
            if ($evento->caminho_brasao) Storage::disk('public')->delete($evento->caminho_brasao);
            $dadosUpdate['caminho_brasao'] = null;
        }
        if ($request->hasFile('imagem_brasao')) {
            if ($evento->caminho_brasao && !isset($dadosUpdate['caminho_brasao'])) {
                Storage::disk('public')->delete($evento->caminho_brasao);
            }
            $dadosUpdate['caminho_brasao'] = $request->file('imagem_brasao')->store('eventos/brasoes', 'public');
        }

        // Salva as alterações no evento principal
        $evento->update($dadosUpdate);

        // =========================================================
        // D. PROCESSAMENTO DE ASSINATURAS (Adicionar/Editar)
        // =========================================================
        if ($request->has('assinaturas')) {
            $assinaturasData = $request->input('assinaturas');

            foreach ($assinaturasData as $index => $data) {
                // Pula entradas vazias
                if (empty($data['nome'])) continue;

                $pathAssinatura = null;

                // Upload da imagem da assinatura (se enviada)
                if ($request->hasFile("assinaturas.{$index}.arquivo")) {
                    $file = $request->file("assinaturas.{$index}.arquivo");
                    $pathAssinatura = $file->store('eventos/assinaturas', 'public');
                }

                if (!empty($data['id'])) {
                    // --- ATUALIZAR EXISTENTE ---
                    $ass = Assinatura::find($data['id']);
                    if ($ass) {
                        $updateData = [
                            'nome' => $data['nome'],
                            'cargo' => $data['cargo']
                        ];

                        // Se houve novo upload, deleta a antiga e salva a nova
                        if ($pathAssinatura) {
                            if($ass->arquivo_assinatura) Storage::disk('public')->delete($ass->arquivo_assinatura);
                            $updateData['arquivo_assinatura'] = $pathAssinatura;
                        }

                        $ass->update($updateData);
                    }
                } else {
                    // --- CRIAR NOVA ---
                    $evento->assinaturas()->create([
                        'nome' => $data['nome'],
                        'cargo' => $data['cargo'],
                        'arquivo_assinatura' => $pathAssinatura
                    ]);
                }
            }
        }

        return redirect()->route('admin.eventos.index')->with('success', 'Evento e Layout atualizados com sucesso!');
    }

    /**
     * Remove o evento.
     */
    public function destroy(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        // Limpeza de arquivos ao deletar evento
        if($evento->caminho_fundo) Storage::disk('public')->delete($evento->caminho_fundo);
        if($evento->caminho_brasao) Storage::disk('public')->delete($evento->caminho_brasao);

        foreach($evento->assinaturas as $ass) {
            if($ass->arquivo_assinatura) Storage::disk('public')->delete($ass->arquivo_assinatura);
        }

        $evento->delete();
        return redirect()->route('admin.eventos.index')->with('success', 'Evento excluído.');
    }

    /**
     * Lista todos os inscritos no evento (Geral).
     */
    public function inscritos(Request $request, Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        $search = $request->search;

        $inscricoes = $evento->inscricoes()
            ->with(['participante.atividadesInscritas'])
            ->whereHas('participante', function($q) use ($search) {
                if ($search) {
                    $q->where('nome_completo', 'like', "%{$search}%")
                      ->orWhere('cpf', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                }
            })
            ->paginate(20);

        return view('admin.eventos.inscritos', compact('evento', 'inscricoes'));
    }

    /**
     * Exporta a lista geral de inscritos (CSV).
     */
    public function exportarInscritos(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        $nomeArquivo = 'Inscritos-' . Str::slug($evento->titulo) . '.csv';

        $inscricoes = $evento->inscricoes()
                             ->with(['participante.atividadesInscritas', 'participante.turma'])
                             ->get();

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$nomeArquivo",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        $callback = function() use ($inscricoes) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM para Excel

            fputcsv($file, ['Nome', 'CPF', 'Email', 'Vínculo', 'Matrícula', 'Turma', 'Atividades Selecionadas', 'Data Inscrição'], ';');

            foreach ($inscricoes as $row) {
                // Lista atividades selecionadas
                $atividades = $row->participante->atividadesInscritas
                    ->where('evento_id', $row->evento_id)
                    ->pluck('titulo')
                    ->implode(', ');

                fputcsv($file, [
                    $row->participante->nome_completo,
                    $row->participante->cpf,
                    $row->participante->email,
                    ucfirst($row->participante->tipo_vinculo),
                    $row->participante->matricula ?? '-',
                    $row->participante->turma->nome_completo ?? '-',
                    $atividades ?: 'Nenhuma',
                    $row->created_at->format('d/m/Y H:i')
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
