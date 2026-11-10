@php
    $usaConta = true;
    $msg = trim((string) (($exception ?? null)?->getMessage() ?? ''));
    $padrao = in_array(mb_strtolower($msg), ['', 'this action is unauthorized.', 'forbidden'], true);
@endphp
@extends('errors.layout')
@section('codigo', '403')
@section('titulo', 'Acesso negado')
@section('mensagem', $padrao ? 'Você não tem permissão para abrir esta página.' : $msg)
@section('dica')Se você acha que deveria ter acesso, confira se entrou com a conta certa ou fale com a organização do evento.@endsection
@section('rodape', 'Código 403')
