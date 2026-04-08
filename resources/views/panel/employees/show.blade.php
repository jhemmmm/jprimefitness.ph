@extends('panel.layouts.app')

@section('title', $employeeName . ' - Employee')

@section('content')
    <employee-detail-page :employee='@json($employee)' :branches-data='@json($branches)'
        :roles-data='@json($roles)'></employee-detail-page>
@endsection
