@extends('panel.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <dashboard-page :business-profile='@json($businessProfile)'></dashboard-page>
@endsection
