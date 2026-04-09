@extends('panel.layouts.app')

@section('title', 'Business Settings')

@section('content')
    <business-settings-page :profile='@json($businessProfile)'></business-settings-page>
@endsection
