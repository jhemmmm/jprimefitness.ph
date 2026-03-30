@extends('panel.layouts.app')

@section('title', 'Attendance Reports')

@section('content')
    <attendance-reports-page :branches-data='@json($branches)'></attendance-reports-page>
@endsection
