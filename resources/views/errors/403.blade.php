@extends('errors.layout')

@section('title', 'Access Denied')
@section('code', '403')
@section('heading', 'Access Denied')
@section('message', 'Your account does not have permission for this route or action.')

@section('actions')
  <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
    <i class="fa-solid fa-gauge-high me-2"></i>
    Dashboard
  </a>
@endsection
