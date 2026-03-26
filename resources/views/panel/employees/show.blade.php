@extends('panel.layouts.app')

@section('title', $employee->name . ' — Employee')

@section('content')
    <employee-detail-page :employee='@json($employee)'
        :branches-data='@json($branches)'></employee-detail-page>
@endsection
