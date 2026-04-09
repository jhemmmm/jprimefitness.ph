@extends('panel.layouts.app')

@section('title', 'Business Photos')

@section('content')
    <business-photos-page :profile='@json($businessProfile)'></business-photos-page>
@endsection
