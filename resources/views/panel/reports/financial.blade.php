@extends('panel.layouts.app')

@section('title', 'Financial Reports')

@section('content')
    <financial-reports-page :branches-data='@json($branches)'></financial-reports-page>
@endsection
