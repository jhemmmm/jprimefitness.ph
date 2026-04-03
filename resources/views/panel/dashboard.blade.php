@extends('panel.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <dashboard-page :branches-data='@json($branches)'></dashboard-page>
@endsection
