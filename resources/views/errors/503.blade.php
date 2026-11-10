@php $usaConta = false; @endphp
@extends('errors.layout')
@section('codigo', '503')
@section('titulo', 'Voltamos já')
@section('mensagem', 'O sistema está em manutenção rápida. Tente de novo em instantes.')
@section('acao-principal')<button type="button" class="btn p" onclick="location.reload()">Tentar de novo</button>@endsection
@section('rodape', 'Código 503')
