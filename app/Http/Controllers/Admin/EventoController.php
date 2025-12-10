<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Assinatura;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
        // Apenas o evento é carregado para a view. A variável $users foi removida.
        return view('admin.eventos.edit', compact('evento'));
    }
    /**
     * Atualiza o evento, layout, textos e assinaturas.
     */
    public function update(Request $request, Evento $evento)
    {
        // 1. Validação Apenas dos Dados Primários
        $validator = Validator::make($request->all(), [
            'titulo' => 'required|string|max:255',
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
            'local' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'cor_fundo' => 'nullable|string|size:7|starts_with:#',

            // Geolocalização
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'raio_permitido' => 'nullable|integer|min:50',

            // TODOS os campos de Certificado (cert_corpo, imagem_fundo, assinaturas) REMOVIDOS daqui.
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();


        // 2. GESTÃO DE IMAGENS/ASSINATURAS: REMOVIDA
        // Os caminhos de 'caminho_fundo' e 'caminho_brasao' não são mais manipulados aqui.


        // 3. Atualização de Dados Principais e Configurações
        $evento->fill($validated);

        // Atualiza configurações de cor
        $configuracoes = $evento->configuracoes ?? [];
        $configuracoes['cor_fundo'] = $request->cor_fundo;
        $evento->configuracoes = $configuracoes;

        $evento->save();


        // 4. GESTÃO DE ASSINATURAS: REMOVIDA

        return redirect()->route('admin.eventos.show', $evento->id)->with('success', 'Dados do Evento atualizados com sucesso!');
    }
    /**
     * Remove o evento.
     */


    public function editCertificado(Evento $evento)
    {
        // Carrega dados para as assinaturas (Model Assinatura deve estar disponível)
        $dadosAssinaturas = $evento->assinaturas;

        return view('admin.eventos.certificado.edit', compact('evento', 'dadosAssinaturas'));
    }

    /**
     * Atualiza apenas os dados de Layout e Assinaturas do Certificado.
     */
    public function updateCertificado(Request $request, Evento $evento)
    {
        // 1. Validação (Focada apenas em Certificado)
        $validator = Validator::make($request->all(), [
            'cert_cabecalho' => 'nullable|string',
            'cert_corpo' => 'required|string',
            'cert_rodape' => 'nullable|string',

            'imagem_fundo' => 'nullable|image|max:5120',
            'imagem_brasao' => 'nullable|image|max:2048',

            'assinaturas' => 'nullable|array',
            'assinaturas.*.nome' => 'required|string|max:255',
            'assinaturas.*.cargo' => 'required|string|max:255',
            // O upload de arquivo de assinatura também será validado aqui
            'assinaturas.*.arquivo' => 'nullable|image|max:512',
            'assinaturas_para_remover' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        // Define o diretório base de destino dentro de storage/app/public
        $targetDir = 'eventos/certificados';

        // 2. GESTÃO DE IMAGENS DE FUNDO E BRASÃO (USANDO MOVE() E CORRIGINDO O IF/ELSE)

        // --- Imagem de Fundo (caminho_fundo) ---
        $caminhoFundoAtual = $evento->caminho_fundo;

        // CORREÇÃO: Verifica se 'remover_fundo' está presente E se o valor é estritamente '1'
        if ($request->input('remover_fundo') == '1') {
            // Bloco de Remoção
            if ($caminhoFundoAtual) Storage::disk('public')->delete($caminhoFundoAtual);
            $evento->caminho_fundo = null;
        } elseif ($request->hasFile('imagem_fundo')) {
            // Bloco de Upload
            $file = $request->file('imagem_fundo');

            // 1. Deleta o antigo (se houver)
            if ($caminhoFundoAtual) Storage::disk('public')->delete($caminhoFundoAtual);

            // 2. Cria nome único e move o arquivo (estratégia move)
            $fileName = time() . '_fundo.' . $file->getClientOriginalExtension();
            $file->move(storage_path('app/public/' . $targetDir), $fileName);

            // 3. Salva o novo caminho (relativo a storage/app/public)
            $evento->caminho_fundo = $targetDir . '/' . $fileName;
        }

        // --- Imagem do Brasão (caminho_brasao) ---
        $caminhoBrasaoAtual = $evento->caminho_brasao;

        // CORREÇÃO: Verifica se 'remover_brasao' está presente E se o valor é estritamente '1'
        if ($request->input('remover_brasao') == '1') {
            // Bloco de Remoção
            if ($caminhoBrasaoAtual) Storage::disk('public')->delete($caminhoBrasaoAtual);
            $evento->caminho_brasao = null;
        } elseif ($request->hasFile('imagem_brasao')) {
            // Bloco de Upload
            $file = $request->file('imagem_brasao');

            // 1. Deleta o antigo (se houver)
            if ($caminhoBrasaoAtual) Storage::disk('public')->delete($caminhoBrasaoAtual);

            // 2. Cria nome único e move o arquivo (estratégia move)
            $fileName = time() . '_brasao.' . $file->getClientOriginalExtension();
            $file->move(storage_path('app/public/' . $targetDir), $fileName);

            // 3. Salva o novo caminho
            $evento->caminho_brasao = $targetDir . '/' . $fileName;
        }


        // 3. Atualização de Dados Principais do Certificado (Textos)

        // Remove as chaves de arquivo para evitar preencher o modelo com elas
        unset($validated['imagem_fundo'], $validated['imagem_brasao']);

        $evento->cert_cabecalho = $validated['cert_cabecalho'];
        $evento->cert_corpo = $validated['cert_corpo'];
        $evento->cert_rodape = $validated['cert_rodape'];

        $evento->save();


        // 4. Gestão de Assinaturas (Criação, Atualização e Remoção)
        $assinaturasData = $request->input('assinaturas', []);
        $idsParaRemover = array_filter(explode(',', $request->input('assinaturas_para_remover', '')));

        // A) REMOÇÃO
        if (!empty($idsParaRemover)) {
            $assinaturasParaRemover = $evento->assinaturas()->whereIn('id', $idsParaRemover)->get();
            foreach ($assinaturasParaRemover as $assinatura) {
                if ($assinatura->arquivo_assinatura) {
                    Storage::disk('public')->delete($assinatura->arquivo_assinatura);
                }
                $assinatura->delete();
            }
        }

        // B) CRIAÇÃO/ATUALIZAÇÃO
        foreach ($assinaturasData as $index => $data) {

            $assinaturaId = $data['id'];

            if ($assinaturaId) {
                $assinatura = Assinatura::findOrFail($assinaturaId);
            } else {
                $assinatura = new Assinatura(['evento_id' => $evento->id]);
            }

            $assinatura->nome = $data['nome'];
            $assinatura->cargo = $data['cargo'];

            $arquivo = $request->file("assinaturas.{$index}.arquivo");

            if ($arquivo && $arquivo->isValid()) {
                // Manteremos o store() aqui, assumindo que funciona para uploads menores
                if ($assinatura->arquivo_assinatura) {
                    Storage::disk('public')->delete($assinatura->arquivo_assinatura);
                }
                $assinatura->arquivo_assinatura = $arquivo->store("eventos/{$evento->id}/assinaturas", 'public');
            }

            $assinatura->save();
        }

        return redirect()->route('admin.eventos.certificado.edit', $evento->id)->with('success', 'Configurações de Certificado atualizadas com sucesso!');
    }
    public function destroy(Evento $evento)
    {
        if ($evento->criado_por !== Auth::id()) abort(403);

        // Limpeza de arquivos ao deletar evento
        if ($evento->caminho_fundo) Storage::disk('public')->delete($evento->caminho_fundo);
        if ($evento->caminho_brasao) Storage::disk('public')->delete($evento->caminho_brasao);

        foreach ($evento->assinaturas as $ass) {
            if ($ass->arquivo_assinatura) Storage::disk('public')->delete($ass->arquivo_assinatura);
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
            ->whereHas('participante', function ($q) use ($search) {
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

        $callback = function () use ($inscricoes) {
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
