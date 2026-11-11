<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/** Recebe as imagens inseridas no editor de texto da descrição do evento e devolve o endereço público. */
class EventoImagemController extends Controller
{
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'upload' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ], [
            'upload.required' => 'Selecione uma imagem.',
            'upload.image' => 'O arquivo precisa ser uma imagem.',
            'upload.mimes' => 'Use imagens JPG, PNG, WebP ou GIF.',
            'upload.max' => 'A imagem pode ter no máximo 4 MB.',
            'upload.uploaded' => 'Não foi possível enviar a imagem (tamanho acima do permitido pelo servidor).',
        ]);

        // Formato que o editor espera para mostrar o erro ao usuário
        if ($v->fails()) {
            return response()->json(['error' => ['message' => $v->errors()->first()]], 422);
        }

        $caminho = $request->file('upload')->store('eventos/descricoes/' . now()->format('Y/m'), 'public');

        // Caminho relativo ao site ("/storage/..."): continua valendo se o domínio, a porta ou o https mudarem
        $url = parse_url(Storage::disk('public')->url($caminho), PHP_URL_PATH) ?: '/storage/' . $caminho;

        return response()->json(['url' => $url]);
    }
}
