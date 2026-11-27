@php $n = $noticia ?? null; $upload = \Illuminate\Support\Facades\Route::has('admin.eventos.imagens.store') ? route('admin.eventos.imagens.store') : null; @endphp
<div class="mb-3">
    <label class="small fw-bold" for="titulo">Título</label>
    <input type="text" name="titulo" id="titulo" class="form-control" maxlength="160" required value="{{ old('titulo', $n?->titulo) }}" placeholder="Ex.: Programação atualizada">
</div>
<div class="mb-3">
    <label class="small fw-bold">Texto</label>
    <x-editor-rico name="conteudo" :value="old('conteudo', $n?->conteudoHtml())" :upload-url="$upload" :altura="220" />
</div>
<div class="row g-3 align-items-end">
    <div class="col-md-5">
        <label class="small fw-bold" for="publicado_em">Publicar em</label>
        <input type="datetime-local" name="publicado_em" id="publicado_em" class="form-control" value="{{ old('publicado_em', $n?->publicado_em?->format('Y-m-d\TH:i')) }}">
        <div class="form-text">Em branco: publica agora. Data futura: aparece no site só quando chegar.</div>
    </div>
    <div class="col-md-7">
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="publicada" value="1" id="publicada" @checked(old('_token') ? old('publicada') : ($n?->publicada ?? true))><label class="form-check-label" for="publicada">Visível no site (desligado fica como rascunho)</label></div>
        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="fixada" value="1" id="fixada" @checked(old('_token') ? old('fixada') : ($n?->fixada ?? false))><label class="form-check-label" for="fixada">Fixar no topo (destaque)</label></div>
    </div>
</div>
