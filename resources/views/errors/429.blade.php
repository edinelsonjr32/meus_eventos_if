@php $usaConta = true; @endphp
@extends('errors.layout')
@section('codigo', '429')
@section('titulo', 'Muitas tentativas')
@section('mensagem', 'Você fez muitos pedidos em pouco tempo. Aguarde cerca de 1 minuto e tente de novo.')
@section('rodape', 'Código 429')
