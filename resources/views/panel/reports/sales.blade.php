@extends('panel.layouts.app')

@section('title', 'Sales Reports')

@section('content')
    <sales-reports-page :branches-data='@json($branches)'></sales-reports-page>
@endsection
