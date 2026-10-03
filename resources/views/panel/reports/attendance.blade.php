@extends('panel.layouts.app')

@section('title', 'Attendance Reports')

@section('content')
    <attendance-reports-page :business-profile='@json($businessProfile)'></attendance-reports-page>
@endsection
