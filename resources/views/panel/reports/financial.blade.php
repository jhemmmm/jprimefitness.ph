@extends('panel.layouts.app')

@section('title', 'Financial Reports')

@section('content')
    <financial-reports-page :business-profile='@json($businessProfile)'></financial-reports-page>
@endsection
