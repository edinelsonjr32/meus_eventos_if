@php $usaConta = true; @endphp
@extends('errors.layout')
@section('codigo', '419')
@section('titulo', 'A página expirou')
@section('mensagem', 'Ficou muito tempo aberta e o formulário perdeu a validade. Nada foi salvo.')
@section('dica')Volte, recarregue a página e envie de novo. Se acontecer outra vez, entre na sua conta novamente.@endsection
@section('rodape', 'Código 419')
