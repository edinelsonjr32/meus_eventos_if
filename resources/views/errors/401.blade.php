@php $usaConta = true; @endphp
@extends('errors.layout')
@section('codigo', '401')
@section('titulo', 'Faça login para continuar')
@section('mensagem', 'Esta página só abre para quem está com a conta ativa.')
@section('acao-principal')<a class="btn p" href="{{ url('/login') }}">Entrar</a>@endsection
@section('rodape', 'Código 401')
