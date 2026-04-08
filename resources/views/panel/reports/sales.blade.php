@extends('panel.layouts.app')

@section('title', 'Sales Reports')

@section('content')
    <sales-reports-page :business-profile='@json($businessProfile)'></sales-reports-page>
@endsection
