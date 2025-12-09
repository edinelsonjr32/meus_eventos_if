<div class="mt-4">
    <button type="button"
            @click="submitForm()"
            :disabled="isLoading"
            {{ $attributes->merge(['class' => 'btn btn-success-custom text-white shadow w-100 py-3 fw-bold d-flex align-items-center justify-content-center transition-all']) }}>

        <template x-if="isLoading">
            <span class="d-flex align-items-center gap-2 fade-in">
                <i class="fa-solid fa-satellite-dish fa-bounce"></i>
                <span>Buscando Localização...</span>
            </span>
        </template>

        <template x-if="!isLoading && lat && lng">
            <span class="d-flex align-items-center gap-2 fade-in">
                <i class="fa-solid fa-check-circle"></i>
                <span>Confirmar Agora</span>
            </span>
        </template>

        <template x-if="!isLoading && (!lat || !lng)">
            <span class="d-flex align-items-center gap-2 fade-in">
                <i class="fa-solid fa-location-dot"></i>
                <span>Confirmar Presença</span>
            </span>
        </template>
    </button>

    <div class="text-center mt-2" style="height: 20px;">
        <template x-if="isLoading">
            <small class="text-muted fst-italic animate-pulse">
                Aguarde, calibrando GPS...
            </small>
        </template>
        <template x-if="!isLoading && !lat && !lng">
            <small class="text-muted opacity-50" style="font-size: 0.75rem;">
                <i class="fa-solid fa-lock me-1"></i> Validação por GPS necessária
            </small>
        </template>
    </div>

    <style>
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .animate-pulse { animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    </style>
</div>
