@extends('panel.layouts.app')

@section('title', 'Settings')

@section('content')
    <settings-page :profile='@json($businessProfile)'></settings-page>
@endsection
