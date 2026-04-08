@extends('panel.layouts.app')

@section('title', 'Payroll Reports')

@section('content')
    <payroll-reports-page :business-profile='@json($businessProfile)'></payroll-reports-page>
@endsection
