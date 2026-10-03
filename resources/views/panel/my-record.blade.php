@extends('panel.layouts.app')

@section('title', 'My Record')

@section('content')
    <my-record-page :employee='@json($employee)' section="{{ $section }}"></my-record-page>
@endsection
