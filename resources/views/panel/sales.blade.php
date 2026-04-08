@extends('panel.layouts.app')

@section('title', 'Sales')

@section('content')
    <sales-page :profile='@json($businessProfile)'></sales-page>
@endsection
