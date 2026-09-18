@extends('panel.layouts.app')

@section('title', 'PT Client')

@section('content')
    <coach-pt-session-detail-page :client='@json($client)'></coach-pt-session-detail-page>
@endsection
