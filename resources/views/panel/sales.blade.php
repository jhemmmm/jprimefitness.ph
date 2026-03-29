@extends('panel.layouts.app')

@section('title', 'Sales')

@section('content')
    <sales-page :branches-data='@json($branches)'></sales-page>
@endsection
