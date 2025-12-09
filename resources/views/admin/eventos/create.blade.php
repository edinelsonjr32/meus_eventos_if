<x-app-layout>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('admin.eventos.index') }}" class="btn btn-light rounded-circle shadow-sm" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <h3 class="fw-bold m-0">Novo Evento</h3>
                        <small class="text-secondary">Preencha as informações básicas</small>
                    </div>
                </div>
            </div>

            <div class="glass-card mb-4">
                <form action="{{ route('admin.eventos.store') }}" method="POST">
                    @csrf

                    <h6 class="text-uppercase text-secondary fw-bold mb-4" style="font-size: 0.75rem; letter-spacing: 1px;">Dados Gerais</h6>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">TÍTULO</label>
                        <input type="text" name="titulo" class="form-control form-control-lg" placeholder="Ex: II Semana de Tecnologia" value="{{ old('titulo') }}" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">INÍCIO</label>
                            <input type="datetime-local" name="data_inicio" class="form-control" value="{{ old('data_inicio') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">FIM</label>
                            <input type="datetime-local" name="data_fim" class="form-control" value="{{ old('data_fim') }}" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">LOCAL (Texto)</label>
                        <input type="text" name="local" class="form-control" placeholder="Ex: Auditório Central" value="{{ old('local') }}" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small">DESCRIÇÃO</label>
                        <textarea name="descricao" class="form-control" rows="3">{{ old('descricao') }}</textarea>
                    </div>

                    <hr class="my-4 text-secondary opacity-25">
                    <h6 class="text-uppercase text-secondary fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 1px;">
                        <i class="fa-solid fa-location-crosshairs me-2"></i> Segurança de Presença (Geo-fencing)
                    </h6>

                    <div class="alert alert-info border-0 d-flex align-items-center gap-3 mb-3">
                        <i class="fa-solid fa-info-circle fs-4"></i>
                        <small style="line-height: 1.2;">
                            Se preenchido, o aluno só conseguirá registrar presença se estiver dentro do raio permitido. Deixe em branco para desativar.
                            <a href="https://www.google.com/maps" target="_blank" class="fw-bold text-decoration-none">Consultar Google Maps</a>
                        </small>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary small">LATITUDE</label>
                            <input type="text" name="latitude" class="form-control" placeholder="Ex: -1.9234" value="{{ old('latitude') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary small">LONGITUDE</label>
                            <input type="text" name="longitude" class="form-control" placeholder="Ex: -55.4321" value="{{ old('longitude') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold text-secondary small">RAIO (Metros)</label>
                            <input type="number" name="raio_permitido" class="form-control" value="{{ old('raio_permitido', 300) }}">
                        </div>
                    </div>
                    <hr class="my-4 text-secondary opacity-25">

                    <h6 class="text-uppercase text-secondary fw-bold mb-4" style="font-size: 0.75rem; letter-spacing: 1px;">Identidade Visual</h6>

                    <div class="row align-items-center mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary small">COR TEMÁTICA</label>
                            <input type="color" name="cor_fundo" class="form-control form-control-color w-100" value="{{ old('cor_fundo', '#10b981') }}">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-3">
                        <button type="submit" class="btn-primary-custom px-5">
                            Criar Evento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
