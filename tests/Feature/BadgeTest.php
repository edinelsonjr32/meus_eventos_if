<?php

use App\Models\Trabalho;
use Illuminate\Support\Facades\Blade;

it('cada status de trabalho tem cor e ícone próprios (nenhum repetido)', function () {
    $estilos = collect(array_keys(Trabalho::STATUS))->map(function ($s) {
        $html = Blade::render('<x-badge tipo="trabalho" :valor="$s" />', ['s' => $s]);
        preg_match('/background: (#\w+); color: (#\w+)/', $html, $c);
        preg_match('/fa-solid (fa-[\w-]+)/', $html, $i);

        expect($html)->toContain(Trabalho::STATUS[$s])->and($c)->not->toBeEmpty()->and($i)->not->toBeEmpty();

        return $c[1] . $c[2] . $i[1];
    });

    expect($estilos->unique()->count())->toBe($estilos->count());
});

it('o selo aceita rótulo, texto extra e valor desconhecido sem quebrar', function () {
    expect(Blade::render('<x-badge tipo="avaliacao" valor="concluida" extra="8,50" />'))->toContain('Concluída')->toContain('8,50')
        ->and(Blade::render('<x-badge tipo="trabalho" valor="aprovado" rotulo="Aprovado!" :icone="false" />'))->toContain('Aprovado!')->not->toContain('fa-solid')
        ->and(Blade::render('<x-badge tipo="trabalho" valor="status_novo" />'))->toContain('Status novo');
});

it('escapa o texto do selo (sem XSS)', function () {
    $html = Blade::render('<x-badge tipo="certificado" valor="revogado" :extra="$m" />', ['m' => '<script>alert(1)</script>']);
    expect($html)->not->toContain('<script>')->toContain('&lt;script&gt;');
});
