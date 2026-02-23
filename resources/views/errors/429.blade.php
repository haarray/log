@extends('errors.layout')

@section('title', 'Too Many Requests')
@section('code', '429')
@section('heading', 'Too Many Requests')
@section('message', 'Rate limit reached. Wait a moment before trying again.')
@endsection
