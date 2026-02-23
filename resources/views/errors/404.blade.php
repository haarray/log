@extends('errors.layout')

@section('title', 'Page Not Found')
@section('code', '404')
@section('heading', 'Page Not Found')
@section('message', 'The URL you requested does not exist in this starter kit route map.')

@section('actions')
  <a href="{{ route('docs.index') }}" class="btn btn-outline-secondary">
    <i class="fa-solid fa-book-open me-2"></i>
    Open Docs
  </a>
@endsection
