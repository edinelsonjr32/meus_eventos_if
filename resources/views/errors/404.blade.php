@php $usaConta = true; @endphp
@extends('errors.layout')
@section('codigo', '404')
@section('titulo', 'Página não encontrada')
@section('mensagem', 'O endereço não existe, mudou de lugar ou o evento já não está disponível.')
@section('dica')Confira o endereço digitado. Se veio de um QR Code ou de um link, peça um novo à organização do evento.@endsection
@section('rodape', 'Código 404')
