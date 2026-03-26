@extends('panel.layouts.app')

@section('title', 'Attendance')

@section('content')
    <attendance-page :branches-data='@json($branches)'></attendance-page>
@endsection
