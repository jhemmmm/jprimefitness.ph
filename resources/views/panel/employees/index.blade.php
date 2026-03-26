@extends('panel.layouts.app')

@section('title', 'Employees')

@section('content')
    <employees-page :branches-data='@json($branches)'
        :roles-data='@json($roles)'></employees-page>
@endsection
