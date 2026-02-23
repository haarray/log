@extends('errors.layout')

@section('title', 'Server Error')
@section('code', '500')
@section('heading', 'Server Error')
@section('message', 'An unexpected exception occurred while processing this request.')

@section('actions')
  <a href="{{ url('/dashboard') }}" class="btn btn-outline-secondary">
    <i class="fa-solid fa-arrow-left me-2"></i>
    Back to Dashboard
  </a>
@endsection
