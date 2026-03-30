@extends('panel.layouts.app')

@section('title', 'Payroll Reports')

@section('content')
    <payroll-reports-page :branches-data='@json($branches)'></payroll-reports-page>
@endsection
