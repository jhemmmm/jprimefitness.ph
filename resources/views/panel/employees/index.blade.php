@extends('panel.layouts.app')

@section('title', 'Employees')

@section('content')
    <employees-page :roles-data='@json($roles)'></employees-page>
@endsection
