<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\MinistranteEvento;
use App\Services\MinistrantesDoEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Fotos e descrições de quem ministra, exibidas na página pública do evento. */
class MinistranteEventoController extends Controller
{
    public function index(Evento $evento, MinistrantesDoEvento $ministrantes)
    {
        $this->authorize('gerenciar', $evento);

        return view('admin.ministrantes.index', ['evento' => $evento, 'pessoas' => $ministrantes->todos($evento)]);
    }

    /** Cria ou atualiza os dados de uma pessoa (a chave liga o cadastro ao nome escrito na programação). */
    public function salvar(Request $request, Evento $evento)
    {
        $this->authorize('gerenciar', $evento);

        $d = $request->validate([
            'chave' => ['nullable', 'string', 'max:160'],
            'nome' => ['required', 'string', 'min:3', 'max:160'],
            'descricao' => ['nullable', 'string', 'max:160'],
            'ordem' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'nome.required' => 'Informe o nome.',
            'foto.max' => 'A foto pode ter no máximo 2 MB.',
            'foto.image' => 'Envie uma imagem JPG, PNG ou WEBP.',
            'foto.mimes' => 'Envie uma imagem JPG, PNG ou WEBP.',
            'foto.uploaded' => 'Não foi possível receber a foto. Envie um arquivo de até 2 MB.',
        ]);

        $pessoa = MinistranteEvento::firstOrNew([
            'evento_id' => $evento->id,
            'chave' => ($d['chave'] ?? null) ?: MinistranteEvento::chaveDe($d['nome']),
        ]);
        $pessoa->fill([
            'nome' => trim($d['nome']),
            'descricao' => $d['descricao'] ?? null,
            'ordem' => $d['ordem'] ?? null,
            'oculto' => !$request->boolean('visivel'),
        ]);

        if ($request->hasFile('foto') || $request->boolean('remover_foto')) {
            if ($pessoa->caminho_foto) {
                Storage::disk('public')->delete($pessoa->caminho_foto);
            }
            $pessoa->caminho_foto = $request->hasFile('foto') ? $request->file('foto')->store("eventos/{$evento->id}/ministrantes", 'public') : null;
        }
        $pessoa->save();

        return redirect()->route('admin.ministrantes.index', $evento)->with('success', "{$pessoa->nome}: dados salvos.");
    }

    public function destroy(Evento $evento, MinistranteEvento $ministrante)
    {
        $this->authorize('gerenciar', $evento);
        abort_unless($ministrante->evento_id === $evento->id, 404);

        if ($ministrante->caminho_foto) {
            Storage::disk('public')->delete($ministrante->caminho_foto);
        }
        $ministrante->delete();

        return redirect()->route('admin.ministrantes.index', $evento)->with('success', 'Foto e dados removidos. Quem está na programação continua aparecendo com as iniciais.');
    }
}
