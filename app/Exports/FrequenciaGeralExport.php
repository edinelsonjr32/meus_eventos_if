<?php

namespace App\Exports;

use App\Exports\Frequencia\PlanilhaTabela;
use App\Exports\Frequencia\ResumoFrequencia;
use App\Models\Curso;
use App\Models\Evento;
use App\Models\Frequencia;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Planilha de frequência para os professores, em abas: Resumo, Frequência (detalhe), Por aluno, Por atividade e Matriz.
 * Filtros: evento, curso, turma, período das atividades e vínculo (alunos ou todos os participantes).
 */
class FrequenciaGeralExport implements WithMultipleSheets
{
    /** Limite de atividades na matriz (cada atividade vira uma coluna). */
    public const MAX_COLUNAS_MATRIZ = 120;

    private ?Collection $registros = null;

    public function __construct(protected array $filtros, private ?string $geradoPor = null)
    {
    }

    /** Presenças filtradas, já ordenadas por data da atividade, atividade e nome do participante. */
    public function registros(): Collection
    {
        if ($this->registros) {
            return $this->registros;
        }

        $f = $this->filtros;
        $query = Frequencia::query()->with(['participante.turma.curso', 'atividade.evento', 'atividade.localCadastrado'])
            ->whereHas('participante', function ($q) use ($f) {
                if (($f['vinculo'] ?? 'aluno') !== 'todos') {
                    $q->where('tipo_vinculo', 'aluno');
                }
                if (!empty($f['turma_id'])) {
                    $q->where('turma_id', $f['turma_id']);
                }
                if (!empty($f['curso_id'])) {
                    $q->whereHas('turma', fn ($t) => $t->where('curso_id', $f['curso_id']));
                }
            })
            ->whereHas('atividade', function ($q) use ($f) {
                if (!empty($f['evento_id'])) {
                    $q->where('evento_id', $f['evento_id']);
                }
                if (isset($f['eventos_permitidos'])) {
                    $q->whereIn('evento_id', $f['eventos_permitidos']);
                }
                if (!empty($f['data_de'])) {
                    $q->whereDate('data_inicio', '>=', $f['data_de']);
                }
                if (!empty($f['data_ate'])) {
                    $q->whereDate('data_inicio', '<=', $f['data_ate']);
                }
            });

        return $this->registros = $query->get()->sortBy([
            fn ($a, $b) => $a->atividade->data_inicio <=> $b->atividade->data_inicio,
            fn ($a, $b) => $a->atividade_id <=> $b->atividade_id,
            fn ($a, $b) => mb_strtolower($a->participante->nome_completo) <=> mb_strtolower($b->participante->nome_completo),
        ])->values();
    }

    public function sheets(): array
    {
        $r = $this->registros();
        $abas = [
            new ResumoFrequencia($this->descricaoFiltros(), $this->indicadores($r), $this->quadroTurmas($r), $this->geradoPor ?? 'sistema'),
            $this->abaDetalhe($r),
            $this->abaPorAluno($r),
            $this->abaPorAtividade($r),
        ];
        if ($matriz = $this->abaMatriz($r)) {
            $abas[] = $matriz;
        }

        return $abas;
    }

    private function data($carbon): ?float
    {
        return $carbon ? Date::dateTimeToExcel($carbon) : null;
    }

    private function horas($atividade): float
    {
        return (float) ($atividade->carga_horaria ?? 0);
    }

    private function descricaoFiltros(): array
    {
        $f = $this->filtros;
        $evento = !empty($f['evento_id']) ? Evento::find($f['evento_id'])?->titulo : null;
        $curso = !empty($f['curso_id']) ? Curso::find($f['curso_id'])?->nome : null;
        $turma = !empty($f['turma_id']) ? Turma::find($f['turma_id'])?->nome_completo : null;
        $periodo = !empty($f['data_de']) || !empty($f['data_ate'])
            ? (!empty($f['data_de']) ? date('d/m/Y', strtotime($f['data_de'])) : 'início') . ' a ' . (!empty($f['data_ate']) ? date('d/m/Y', strtotime($f['data_ate'])) : 'hoje')
            : 'Todo o período';

        return [
            ['Evento', $evento ?? 'Todos os eventos'],
            ['Curso', $curso ?? 'Todos os cursos'],
            ['Turma', $turma ?? 'Todas as turmas'],
            ['Período das atividades', $periodo],
            ['Participantes', ($f['vinculo'] ?? 'aluno') === 'todos' ? 'Todos os vínculos' : 'Somente alunos'],
        ];
    }

    private function indicadores(Collection $r): array
    {
        return [
            ['Registros de presença', $r->count()],
            ['Participantes distintos', $r->pluck('participante_id')->unique()->count()],
            ['Atividades com presença', $r->pluck('atividade_id')->unique()->count()],
            ['Horas acumuladas (soma das presenças)', round($r->sum(fn ($f) => $this->horas($f->atividade)), 1)],
        ];
    }

    private function quadroTurmas(Collection $r): array
    {
        return $r->groupBy(fn ($f) => $f->participante->turma_id ?: 0)->map(function ($g) {
            $t = $g->first()->participante->turma;

            return [$t?->curso?->nome ?? 'Sem turma', $t ? (string) $t->ano : '—', $g->pluck('participante_id')->unique()->count(), $g->count(), round($g->sum(fn ($f) => $this->horas($f->atividade)), 1)];
        })->sortBy(fn ($l) => mb_strtolower($l[0] . $l[1]))->values()->all();
    }

    private function abaDetalhe(Collection $r): PlanilhaTabela
    {
        $linhas = $r->map(function ($f) {
            $p = $f->participante;
            $a = $f->atividade;

            return [
                $this->data($a->data_inicio), $a->evento?->titulo, $a->titulo, $a->tipoRotulo(), $a->localCadastrado?->rotulo() ?? $a->local, $this->horas($a),
                $p->nome_completo, $p->matricula, $p->turma?->curso?->nome ?? 'N/A', $p->turma ? (string) $p->turma->ano : 'N/A', $p->email,
                $this->data($f->data_registro), ucfirst((string) $f->tipo_participacao), $a->emiteCertificado() ? 'Sim' : 'Não',
            ];
        })->all();

        return new PlanilhaTabela('Frequência',
            ['Data da atividade', 'Evento', 'Atividade', 'Tipo', 'Local', 'Carga horária (h)', 'Aluno', 'Matrícula', 'Curso', 'Turma (ano)', 'E-mail', 'Registrado em', 'Função', 'Gera certificado'],
            $linhas, [14, 30, 38, 16, 24, 12, 34, 16, 32, 11, 32, 18, 14, 12],
            [0 => 'dd/mm/yyyy', 5 => '0.0', 11 => 'dd/mm/yyyy hh:mm']);
    }

    private function abaPorAluno(Collection $r): PlanilhaTabela
    {
        $linhas = $r->groupBy('participante_id')->map(function ($g) {
            $p = $g->first()->participante;
            $ordenado = $g->sortBy('data_registro');

            return [
                $p->nome_completo, $p->matricula, $p->turma?->curso?->nome ?? 'N/A', $p->turma ? (string) $p->turma->ano : 'N/A', $p->email,
                $g->pluck('atividade_id')->unique()->count(), round($g->sum(fn ($f) => $this->horas($f->atividade)), 1),
                $this->data($ordenado->first()->data_registro), $this->data($ordenado->last()->data_registro),
            ];
        })->sortBy(fn ($l) => mb_strtolower($l[0]))->values()->all();

        return new PlanilhaTabela('Por aluno',
            ['Aluno', 'Matrícula', 'Curso', 'Turma (ano)', 'E-mail', 'Atividades', 'Horas (h)', 'Primeira presença', 'Última presença'],
            $linhas, [36, 16, 32, 11, 32, 11, 11, 18, 18], [6 => '0.0', 7 => 'dd/mm/yyyy hh:mm', 8 => 'dd/mm/yyyy hh:mm']);
    }

    private function abaPorAtividade(Collection $r): PlanilhaTabela
    {
        $linhas = $r->groupBy('atividade_id')->map(function ($g) {
            $a = $g->first()->atividade;

            return [$this->data($a->data_inicio), $a->titulo, $a->evento?->titulo, $a->tipoRotulo(), $a->localCadastrado?->rotulo() ?? $a->local, $this->horas($a), $g->count(), $a->emiteCertificado() ? 'Sim' : 'Não'];
        })->sortBy(fn ($l) => $l[0])->values()->all();

        return new PlanilhaTabela('Por atividade',
            ['Data', 'Atividade', 'Evento', 'Tipo', 'Local', 'Carga horária (h)', 'Presentes', 'Gera certificado'],
            $linhas, [14, 40, 30, 16, 24, 12, 11, 12], [0 => 'dd/mm/yyyy', 5 => '0.0']);
    }

    /** Alunos nas linhas × atividades nas colunas, "P" onde houve presença (omitida se houver atividades demais). */
    private function abaMatriz(Collection $r): ?PlanilhaTabela
    {
        $atividades = $r->pluck('atividade')->unique('id')->sortBy('data_inicio')->values();
        if ($atividades->isEmpty() || $atividades->count() > self::MAX_COLUNAS_MATRIZ) {
            return null;
        }

        $cab = ['Aluno', 'Matrícula', 'Curso', 'Turma (ano)'];
        foreach ($atividades as $a) {
            $cab[] = $a->data_inicio->format('d/m') . ' · ' . mb_strimwidth($a->titulo, 0, 38, '…');
        }
        $cab[] = 'Total';

        $porAluno = $r->groupBy('participante_id');
        $linhas = $porAluno->map(function ($g) use ($atividades) {
            $p = $g->first()->participante;
            $presentes = $g->pluck('atividade_id')->all();
            $linha = [$p->nome_completo, $p->matricula, $p->turma?->curso?->nome ?? 'N/A', $p->turma ? (string) $p->turma->ano : 'N/A'];
            foreach ($atividades as $a) {
                $linha[] = in_array($a->id, $presentes, true) ? 'P' : '';
            }
            $linha[] = count(array_unique($presentes));

            return $linha;
        })->sortBy(fn ($l) => mb_strtolower($l[0]))->values()->all();

        $larguras = [34, 16, 30, 11];
        foreach ($atividades as $_) {
            $larguras[] = 5;
        }
        $larguras[] = 8;

        return new PlanilhaTabela('Matriz', $cab, $linhas, $larguras, [], 4);
    }
}
