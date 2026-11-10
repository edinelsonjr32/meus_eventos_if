@php
    $usaConta = false;
    $ref = app()->bound('erro.ref') ? app('erro.ref') : null;
@endphp
@extends('errors.layout')
@section('codigo', '500')
@section('titulo', 'Algo deu errado do nosso lado')
@section('mensagem', 'Não foi possível concluir o que você pediu. O problema foi registrado.')
@section('dica')
    Volte e tente de novo. Se continuar, avise a organização do evento informando o que você estava fazendo.
    @if($ref)
        <br><strong>Código do erro: <span style="font-family:ui-monospace,Menlo,monospace;letter-spacing:.06em">{{ $ref }}</span></strong>
    @endif
@endsection
@section('rodape', 'Código 500' . ($ref ? ' · ref. ' . $ref : ''))
