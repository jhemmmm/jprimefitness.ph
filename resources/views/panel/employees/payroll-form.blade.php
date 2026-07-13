@extends('panel.layouts.app')

@section('title', ($payroll ? 'Edit Payroll' : 'Create Payroll') . ' - ' . $employeeName)

@section('content')
    <payroll-document-page :employee='@json($employee)' :payroll='@json($payroll)'></payroll-document-page>
@endsection
