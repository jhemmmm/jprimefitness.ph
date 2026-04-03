@extends('panel.layouts.app')

@section('title', $branch->name . ' - Branch')

@section('content')
    <branch-detail-page :branch='@json($branch)'></branch-detail-page>
@endsection
